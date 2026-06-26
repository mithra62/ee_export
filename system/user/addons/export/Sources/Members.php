<?php

namespace Mithra62\Export\Sources;

use CI_DB_result;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

/**
 * Members source — exports EE member rows including custom member fields.
 *
 * Streaming is now supported. Members are fetched in configurable chunks
 * (chunk_size, default 500) using raw CI DB queries so memory stays constant
 * regardless of site size.
 *
 * Custom member fields are lazy-loaded:
 *   - If no custom member fields exist the member_data JOIN is skipped entirely.
 *   - Field definitions are loaded once in openStream() and cached for the
 *     lifetime of the export, not re-fetched per row.
 *   - Custom field values are routed through FieldsService using the same
 *     handler pipeline as Entries, Grid, and Fluid sources.
 *
 * Filters available:
 *   roles             pipe-separated primary role IDs
 *   join_start        join date from (PHP-parseable date string)
 *   join_end          join date to
 *   last_login_start  last visit from
 *   last_login_end    last visit to
 *   limit             max members to export
 *   offset            pagination offset
 *   chunk_size        members per streaming chunk (default 500)
 *   search:field      field-level search filter
 */
class Members extends AbstractSource
{
    // ── Streaming state ──────────────────────────────────────────────────────

    protected int $stream_offset = 0;
    protected int $stream_chunk_size = 500;

    /**
     * Custom member field definitions, keyed by m_field_id.
     * Built once in openStream(); empty when no custom fields are defined.
     *
     * Each entry: {field_id, field_name, field_type, field_label, field_settings, column_key}
     * where column_key is the literal DB column name (m_field_id_X).
     *
     * @var array<int, array>
     */
    protected array $custom_fields = [];

    /** True only when at least one custom member field exists. */
    protected bool $has_custom_fields = false;

    // ── CP form fields ────────────────────────────────────────────────────────

    public function getCpFields(array $context = []): array
    {
        $norm_date = function (array $c, string $key): string {
            $raw = $c['settings'][$key] ?? '';
            if ($raw === '') {
                return '';
            }
            $ts = is_numeric($raw) ? (int) $raw : @strtotime($raw);
            return ($ts && $ts !== -1) ? date('Y-m-d', $ts) : $raw;
        };

        $date_field = function (string $name, string $label) use ($norm_date): array {
            return [
                'name' => $name, 'type' => 'html', 'label' => $label,
                'content_callback' => fn($c) => '<input type="date" name="' . $c['field_name']
                    . '" value="' . htmlspecialchars($norm_date($c, $name)) . '" class="form-control">',
            ];
        };

        return [
            [
                'name' => 'roles', 'type' => 'checkbox', 'label' => 'export_field_roles',
                'choices_callback' => fn($c) => $c['cp']->getMemberRoles(),
                'value_callback' => function ($c) {
                    $raw = $c['settings']['roles'] ?? [];
                    return is_string($raw) ? array_values(array_filter(explode('|', $raw))) : $raw;
                },
            ],
            $date_field('join_start', 'export_field_join_start'),
            $date_field('join_end', 'export_field_join_end'),
            $date_field('last_login_start', 'export_field_last_login_start'),
            $date_field('last_login_end', 'export_field_last_login_end'),
            ['name' => 'limit', 'type' => 'text', 'label' => 'export_field_limit'],
            ['name' => 'offset', 'type' => 'text', 'label' => 'export_field_offset', 'default' => '0'],
            ['name' => 'chunk_size', 'type' => 'text', 'label' => 'export_field_chunk_size', 'default' => '500'],
        ];
    }

    // ── AbstractSource contract ───────────────────────────────────────────────

    public function compile(): AbstractSource
    {
        $this->openStream();
        $rows = [];
        while (true) {
            $chunk = $this->nextChunk();
            if (empty($chunk)) {
                break;
            }
            foreach ($chunk as $row) {
                $rows[] = $row;
            }
        }
        $this->closeStream();

        if (empty($rows)) {
            throw new NoDataException("Nothing to export from your query");
        }

        $this->setExportData($rows);
        return $this;
    }

    // ── Streaming interface ───────────────────────────────────────────────────

    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * Initialise stream state and lazy-load custom field definitions.
     *
     * Custom fields are loaded here rather than per-row so the MemberField
     * ORM query runs exactly once. If no custom member fields are configured
     * we skip the definition load and later skip the member_data JOIN entirely.
     */
    public function openStream(): void
    {
        $this->stream_offset = (int)$this->getOption('offset', 0);
        $this->stream_chunk_size = (int)$this->getOption('chunk_size', 500);

        // Lazy-load: only pay for the MemberField query when custom fields exist
        $raw_fields = ee('export:MemberService')->getFields();
        if (!empty($raw_fields)) {
            // Validate column existence before building the SELECT list.
            // exp_member_fields records and the exp_member_data schema can fall
            // out of sync (backup restore, manual field creation, failed upgrade)
            // and selecting a column that doesn't exist produces a fatal SQL error.
            $actual_columns = ee('export:MemberService')->getMemberDataColumns();

            foreach ($raw_fields as $field) {
                $settings = $field->m_field_settings ?? [];
                if (is_string($settings)) {
                    $settings = @unserialize($settings) ?: [];
                }

                $fid = (int)$field->m_field_id;
                $column_key = 'm_field_id_' . $fid;

                // Skip fields whose column doesn't actually exist in exp_member_data
                if (!isset($actual_columns[$column_key])) {
                    continue;
                }

                $this->custom_fields[$fid] = [
                    'field_id' => $fid,
                    'field_name' => $field->m_field_name,
                    'field_type' => $field->m_field_type,
                    'field_label' => $field->m_field_label,
                    'field_settings' => is_array($settings) ? $settings : [],
                    'column_key' => $column_key,
                ];
            }

            $this->has_custom_fields = !empty($this->custom_fields);
        }
    }

    public function nextChunk(): array
    {
        $limit = $this->stream_chunk_size;

        if ($this->getOption('limit')) {
            $hard_limit = (int)$this->getOption('limit') + (int)$this->getOption('offset', 0);
            $remaining = $hard_limit - $this->stream_offset;
            if ($remaining <= 0) {
                return [];
            }
            $limit = min($limit, $remaining);
        }

        // ── Build query ───────────────────────────────────────────────────────
        //
        // Always select all core member columns.
        // When custom fields exist, select only the m_field_id_X columns from
        // member_data (not member_id again) and LEFT JOIN the table.
        // Skipping the JOIN when there are no custom fields avoids an unnecessary
        // table scan on large installations.
        //
        $query = ee()->db->select('members.*')->from('members');

        if ($this->has_custom_fields) {
            foreach ($this->custom_fields as $field_info) {
                $query->select('member_data.' . $field_info['column_key']);
            }
            $query->join('member_data', 'members.member_id = member_data.member_id', 'left');
        }

        // ── Filters ───────────────────────────────────────────────────────────

        if ($this->getOption('roles')) {
            $roles = $this->getOption('roles');
            if (is_string($roles)) {
                $roles = array_values(array_filter(array_map('trim', explode('|', $roles))));
            }
            if (!empty($roles)) {
                $query->where_in('members.role_id', $roles);
            }
        }

        if ($this->getOption('join_start') && $this->getOption('join_end')) {
            $query->where('members.join_date >=', strtotime($this->getOption('join_start')));
            $query->where('members.join_date <=', strtotime($this->getOption('join_end')));
        }

        if ($this->getOption('last_login_start') && $this->getOption('last_login_end')) {
            // last_visit is a Unix timestamp (INT). Apply strtotime() so a date
            // string like '2024-01-01' is converted before comparison — MySQL
            // would otherwise implicitly cast it to the integer 2024 and silently
            // produce wrong results.
            $query->where('members.last_visit >=', strtotime($this->getOption('last_login_start')));
            $query->where('members.last_visit <=', strtotime($this->getOption('last_login_end')));
        }

        $search = $this->getOption('search', []);
        if (!empty($search)) {
            $this->applySearchFilters($query, $search);
        }

        // ── Execute ───────────────────────────────────────────────────────────

        $result = $query->limit($limit, $this->stream_offset)->get();

        if (!($result instanceof CI_DB_result) || $result->num_rows() === 0) {
            return [];
        }

        $rows = [];
        foreach ($result->result_array() as $member_row) {
            $rows[] = $this->buildRow($member_row);
        }

        $this->stream_offset += count($rows);

        return $rows;
    }

    public function closeStream(): void
    {
    }

    // ── Row assembly ──────────────────────────────────────────────────────────

    /**
     * Build an export row from a raw DB result array.
     *
     * Core member columns are included as-is (arrays JSON-encoded).
     * Custom field columns (m_field_id_X) are skipped in the core pass and
     * instead populated in a separate pass that routes each value through
     * FieldsService, outputting under the human-readable field name.
     *
     * The m_field_ft_X "format" columns carried by the ORM are never present in
     * raw query results so no filtering is needed.
     */
    protected function buildRow(array $member_row): array
    {
        $return = [];
        $member_id = (int)($member_row['member_id'] ?? 0);

        // Core columns
        foreach ($member_row as $key => $value) {
            // Skip raw custom-field columns — handled below under readable names
            if (str_starts_with($key, 'm_field_')) {
                continue;
            }
            $return[$key] = is_array($value) ? json_encode($value) : $value;
        }

        // Custom member fields — processed through FieldsService
        if ($this->has_custom_fields) {
            foreach ($this->custom_fields as $field_info) {
                $raw_value = $member_row[$field_info['column_key']] ?? null;
                $return[$field_info['field_name']] = $this->processFieldValue(
                    $raw_value,
                    $field_info,
                    $member_id
                );
            }
        }

        return $this->cleanFields($return);
    }

    // ── Field handler pipeline ────────────────────────────────────────────────

    /**
     * Route a single custom member field value through the FieldsService handler
     * for its field type, falling back to the raw value when none is registered.
     *
     * The field_info array already matches the AbstractField contract
     * (field_id, field_name, field_type, field_label, field_settings) because it
     * was pre-built from MemberField objects in openStream().
     *
     * Context includes source_type = 'member' and member_id so third-party
     * handlers can distinguish this call-site from Entries, Grid, or Fluid.
     */
    protected function processFieldValue(mixed $raw_value, array $field_info, int $member_id): mixed
    {
        $handler = ee('export:FieldsService')->getField($field_info['field_type']);

        if ($handler) {
            return $handler->process($raw_value, $field_info, $member_id, [
                'source_type' => 'member',
                'member_id' => $member_id,
            ]);
        }

        return $raw_value ?? '';
    }

    // ── Search filters ────────────────────────────────────────────────────────

    /**
     * Apply search:field_name filters to the active query builder instance.
     *
     * Core member columns are qualified with `members.` to avoid ambiguity
     * when member_data is joined. Custom field searches are matched by field
     * name and qualified with `member_data.`.
     */
    protected function applySearchFilters($query, array $search): void
    {
        $columns = ee('export:MemberService')->getColumns();

        foreach ($search as $field_name => $value) {
            if (empty($value)) {
                continue;
            }

            if (isset($columns[$field_name])) {
                // Standard member column
                $query->where('members.' . $field_name, $value);
            } elseif ($this->has_custom_fields) {
                // Custom member field — look up by readable name
                foreach ($this->custom_fields as $field_info) {
                    if ($field_info['field_name'] === $field_name) {
                        $query->where('member_data.' . $field_info['column_key'], $value);
                        break;
                    }
                }
            }
        }
    }
}
