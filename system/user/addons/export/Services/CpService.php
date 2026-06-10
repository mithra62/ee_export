<?php

namespace Mithra62\Export\Services;

/**
 * CpService — utilities used only by the Export Control Panel layer.
 *
 * Centralises channel/field/role lookups that are needed by form dropdowns,
 * AJAX endpoints, and the Run route. Also owns the settings↔params translation
 * used when running a saved configuration from the CP.
 *
 * Form building for Create/Edit has been moved to
 * Mithra62\Export\Forms\CreateEditExport; this class retains only data helpers
 * and the POST → settings converter.
 */
class CpService
{
    // ── Channel & field helpers ───────────────────────────────────────────────

    /**
     * Return all site channels as [channel_id => channel_title].
     */
    public function getChannelList(): array
    {
        $channels = ee('Model')->get('Channel')
            ->filter('site_id', ee()->config->item('site_id'))
            ->order('channel_title', 'asc')
            ->all();

        $list = [];
        foreach ($channels as $channel) {
            $list[(int)$channel->channel_id] = $channel->channel_title;
        }

        return $list;
    }

    /**
     * Return field names for a channel, optionally filtered by field type.
     *
     * When $field_type is provided (e.g. 'grid' or 'fluid_field') only fields
     * of that type are returned, making this suitable for the AJAX field-
     * selector used by the Grid and Fluid form sections.
     *
     * @param int $channel_id
     * @param string|null $field_type EE field type slug, or null for all
     *
     * @return array  [field_id => field_label]
     */
    public function getChannelFields(int $channel_id, ?string $field_type = null): array
    {
        try {
            $channel = ee('Model')
                ->get('Channel')
                ->filter('channel_id', $channel_id)
                ->first();

            if (!$channel) {
                return [];
            }

            // getAllCustomFields() merges fields assigned directly to the channel
            // AND fields belonging to any attached field groups — unlike querying
            // the CustomFields relationship alone, which misses group-assigned fields.
            $all_fields = $channel->getAllCustomFields();

            if (!$all_fields || !count($all_fields)) {
                return [];
            }

            $list = [];
            foreach ($all_fields as $field) {
                if ($field_type && $field->field_type !== $field_type) {
                    continue;
                }
                $list[(int)$field->field_id] = $field->field_label ?: $field->field_name;
            }

            return $list;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Return all member roles as [role_id => name].
     */
    public function getMemberRoles(): array
    {
        $roles = ee('Model')->get('Role')->order('name', 'asc')->all();
        $list = [];
        foreach ($roles as $role) {
            $list[(int)$role->role_id] = $role->name;
        }
        return $list;
    }

    // ── Column preview ────────────────────────────────────────────────────────

    /**
     * Return an ordered list of output column names for a given source + params.
     *
     * Used by the AJAX 'columns' endpoint to populate the whitelist/blacklist
     * checkbox UI. Returns an empty array when the source/channel is ambiguous.
     *
     * @param string $source Source key: entries|members|grid|fluid|sql
     * @param array $params Raw POST params from the form
     *
     * @return string[]
     */
    public function getColumnsForSource(string $source, array $params): array
    {
        switch ($source) {
            case 'entries':
                return $this->columnsForEntries((int)($params['channel_id'] ?? 0));

            case 'members':
                return $this->columnsForMembers();

            case 'grid':
                $channel_id = (int)($params['channel_id'] ?? 0);
                $field_id = (int)($params['field_id'] ?? 0);
                return $this->columnsForGrid($channel_id, $field_id);

            case 'fluid':
                return $this->columnsForFluid();

            case 'sql':
                // Cannot determine columns without executing the query
                return [];
        }

        return [];
    }

    /**
     * Standard columns for an Entries export plus all custom channel fields.
     */
    protected function columnsForEntries(int $channel_id): array
    {
        $core = [
            'entry_id', 'channel_id', 'author_id', 'forum_topic_id', 'ip_address',
            'title', 'url_title', 'status', 'versioning_enabled', 'view_count_one',
            'view_count_two', 'view_count_three', 'view_count_four', 'allow_comments',
            'sticky', 'entry_date', 'year', 'month', 'day', 'expiration_date',
            'comment_expiration_date', 'edit_date', 'recent_comment_date', 'comment_total',
        ];

        if ($channel_id <= 0) {
            return $core;
        }

        $fields = $this->getChannelFields($channel_id);
        foreach ($fields as $fid => $label) {
            $core[] = $label;
        }

        return $core;
    }

    /**
     * Standard + custom member columns.
     */
    protected function columnsForMembers(): array
    {
        $core = ee('export:MemberService')->getColumns();
        $columns = array_keys($core);

        $custom_fields = ee('export:MemberService')->getFields();
        foreach ($custom_fields as $field) {
            $columns[] = $field->m_field_name;
        }

        return $columns;
    }

    /**
     * Grid export columns: entry context + grid field columns.
     */
    protected function columnsForGrid(int $channel_id, int $field_id): array
    {
        $base = ['entry_id', 'entry_title', 'row_order'];

        if ($field_id <= 0) {
            return $base;
        }

        $grid_cols = ee()->db
            ->select('col_name, col_label')
            ->from('grid_columns')
            ->where('field_id', $field_id)
            ->order_by('col_order', 'ASC')
            ->get();

        if ($grid_cols && $grid_cols->num_rows() > 0) {
            foreach ($grid_cols->result_array() as $col) {
                $base[] = $col['col_label'] ?: $col['col_name'];
            }
        }

        return $base;
    }

    /**
     * Fluid export columns are fixed — the output is always the same shape.
     */
    protected function columnsForFluid(): array
    {
        return [
            'entry_id', 'entry_title', 'instance_order',
            'sub_field_id', 'sub_field_type', 'sub_field_label', 'value',
        ];
    }

    // ── Settings ↔ params translation ────────────────────────────────────────

    /**
     * Reconstruct the full $params array that ExportService::setParameters()
     * expects from a stored settings blob + the top-level source key.
     *
     * The settings JSON already contains all source:*, format:*, output:*, and
     * modify:* keys. This method just stitches the 'source' key back in and
     * converts any array-valued 'fields' or 'exclude' back to pipe strings.
     *
     * @param string $source The top-level source key (e.g. 'entries')
     * @param array $settings Decoded settings from ExportConfiguration::getSettings()
     *
     * @return array
     */
    public function buildParamsFromSettings(string $source, array $settings): array
    {
        $params = $settings;
        $params['source'] = $source;

        // Re-join arrays that were serialised as JSON arrays for storage
        if (isset($params['fields']) && is_array($params['fields'])) {
            $params['fields'] = implode('|', array_filter($params['fields']));
        }

        if (isset($params['exclude']) && is_array($params['exclude'])) {
            $params['exclude'] = implode('|', array_filter($params['exclude']));
        }

        // Grid and Fluid store channel/field under source-specific keys to avoid
        // cross-contamination in the editor. Remap them back to source:channel /
        // source:field so the pipeline receives the keys it expects.
        if (in_array($source, ['grid', 'fluid'], true)) {
            foreach (['channel', 'field'] as $k) {
                if (isset($params[$source . ':' . $k])) {
                    $params['source:' . $k] = $params[$source . ':' . $k];
                }
            }
        }

        return $params;
    }

    // ── POST → settings conversion ────────────────────────────────────────────

    /**
     * Convert raw POST data from the Create/Edit form into a settings array
     * ready to be stored as JSON in ExportConfiguration::$settings.
     *
     * @param array $post Raw $_POST
     * @param string $source The selected source key
     *
     * @return array
     */
    public function postToSettings(array $post, string $source): array
    {
        $settings = [];

        // name is a top-level model property but must travel through the settings
        // array to reach the form on validation-failure re-renders (Create and
        // Edit both call postToSettings() then pass the result to renderForm()).
        $settings['name'] = trim($post['name'] ?? '');

        // Template tag access roles — top-level setting, not source-prefixed
        $raw_roles = $post['allowed_roles'] ?? [];
        $settings['allowed_roles'] = array_values(array_filter(
            array_map('intval', is_array($raw_roles) ? $raw_roles : explode('|', (string)$raw_roles))
        ));

        // Source-specific params — strip the `src_{source}_` prefix.
        // Grid and Fluid channel/field are stored under source-specific keys
        // (grid:channel, fluid:field, etc.) so switching source type in the
        // editor never pre-fills the wrong channel or field on the other source.
        $prefix = 'src_' . $source . '_';
        $scoped_params = in_array($source, ['grid', 'fluid'], true) ? ['channel', 'field'] : [];
        foreach ($post as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $param_name = substr($key, strlen($prefix));
                $storage_key = in_array($param_name, $scoped_params, true)
                    ? $source . ':' . $param_name
                    : 'source:' . $param_name;
                $settings[$storage_key] = is_array($value) ? implode('|', $value) : $value;
            }
        }

        // Column selection
        $col_mode = $post['col_mode'] ?? 'all';
        if ($col_mode === 'whitelist') {
            $raw = trim($post['fields'] ?? '');
            $settings['fields'] = $raw ? array_filter(explode('|', $raw)) : [];
            $settings['exclude'] = [];
        } elseif ($col_mode === 'blacklist') {
            $raw = trim($post['exclude'] ?? '');
            $settings['fields'] = [];
            $settings['exclude'] = $raw ? array_filter(explode('|', $raw)) : [];
        } else {
            $settings['fields'] = [];
            $settings['exclude'] = [];
        }

        // Format
        $settings['format'] = $post['format'] ?? 'csv';

        // Single-char fields stored verbatim; all others trimmed
        $char_fields = ['separator', 'enclosure', 'escape'];
        $format_keys = ['separator', 'enclosure', 'escape', 'newline', 'bold_cols', 'sheet_name', 'root_name', 'branch_name'];
        foreach ($format_keys as $fk) {
            $field_name = 'fmt_' . $fk;
            if (!isset($post[$field_name])) {
                continue;
            }
            $val = in_array($fk, $char_fields, true) ? $post[$field_name] : trim($post[$field_name]);
            if ($val !== '') {
                $settings['format:' . $fk] = $val;
            }
        }

        // bold_cols is a checkbox — absent from POST means unchecked
        if ($settings['format'] === 'xlsx' && !isset($post['fmt_bold_cols'])) {
            $settings['format:bold_cols'] = 'n';
        }

        // Output
        $settings['output'] = $post['output'] ?? 'download';
        $settings['output:filename'] = $post['output_filename'] ?? '';
        if (!empty($post['output_path'])) {
            $settings['output:path'] = $post['output_path'];
        }

        // Modifiers — MiniGridInput posts as modify[rows][row_id_N | new_row_N][col]
        // The hidden blank-row template (new_row_0) is always present with empty
        // values; skipping empty column/chain naturally filters it out.
        $modifier_rows = $post['modify']['rows'] ?? [];
        foreach ($modifier_rows as $row) {
            $col = trim($row['column'] ?? '');
            $chain = trim($row['chain'] ?? '');
            if ($col !== '' && $chain !== '') {
                $settings['modify:' . $col] = $chain;
            }
        }

        return $settings;
    }
}
