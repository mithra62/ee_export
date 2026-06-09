<?php

namespace Mithra62\Export\Services;

/**
 * CpService — utilities used only by the Export Control Panel layer.
 *
 * Centralises channel/field/role lookups that are needed by form dropdowns,
 * AJAX endpoints, and the Run route. Also owns the settings↔params translation
 * used when running a saved configuration from the CP.
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
            $list[(int) $channel->channel_id] = $channel->channel_title;
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
     * @param int         $channel_id
     * @param string|null $field_type  EE field type slug, or null for all
     *
     * @return array  [field_id => field_label]
     */
    public function getChannelFields(int $channel_id, ?string $field_type = null): array
    {
        try {
            $channel = ee('Model')
                ->get('Channel')
                ->filter('channel_id', $channel_id)
                ->with('CustomFields')
                ->first();

            if (! $channel || ! $channel->CustomFields) {
                return [];
            }

            $list = [];
            foreach ($channel->CustomFields as $field) {
                if ($field_type && $field->field_type !== $field_type) {
                    continue;
                }
                $list[(int) $field->field_id] = $field->field_label ?: $field->field_name;
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
        $list  = [];
        foreach ($roles as $role) {
            $list[(int) $role->role_id] = $role->name;
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
     * @param string $source  Source key: entries|members|grid|fluid|sql
     * @param array  $params  Raw POST params from the form
     *
     * @return string[]
     */
    public function getColumnsForSource(string $source, array $params): array
    {
        switch ($source) {
            case 'entries':
                return $this->columnsForEntries((int) ($params['channel_id'] ?? 0));

            case 'members':
                return $this->columnsForMembers();

            case 'grid':
                $channel_id = (int) ($params['channel_id'] ?? 0);
                $field_id   = (int) ($params['field_id'] ?? 0);
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
     * @param string $source   The top-level source key (e.g. 'entries')
     * @param array  $settings Decoded settings from ExportConfiguration::getSettings()
     *
     * @return array
     */
    public function buildParamsFromSettings(string $source, array $settings): array
    {
        $params           = $settings;
        $params['source'] = $source;

        // Re-join arrays that were serialised as JSON arrays for storage
        if (isset($params['fields']) && is_array($params['fields'])) {
            $params['fields'] = implode('|', array_filter($params['fields']));
        }

        if (isset($params['exclude']) && is_array($params['exclude'])) {
            $params['exclude'] = implode('|', array_filter($params['exclude']));
        }

        return $params;
    }

    // ── Form helpers ──────────────────────────────────────────────────────────

    /**
     * Build the $vars['sections'] array for ee:_shared/form.
     *
     * All fields use EE's native field-definition format (type, value, choices…).
     * Source- and format-specific rows carry a `group` key so the JS in form.php
     * can show/hide them via [data-group] selectors without any raw-HTML blobs.
     *
     * Only the column-picker and modifier rows remain as `type => 'html'` because
     * they require AJAX-loaded checkboxes and dynamic add/remove rows respectively.
     *
     * @param array  $settings  Decoded settings from ExportConfiguration::getSettings()
     * @param string $source    Currently selected source key
     *
     * @return array
     */
    public function buildFormSections(array $settings, string $source = 'entries'): array
    {
        $channels   = $this->getChannelList();
        $roles      = $this->getMemberRoles();
        $channel_id = (int) ($settings['source:channel'] ?? 0);

        $grid_fields  = $channel_id ? $this->getChannelFields($channel_id, 'grid')        : [];
        $fluid_fields = $channel_id ? $this->getChannelFields($channel_id, 'fluid_field') : [];

        $selected_roles = $settings['source:roles'] ?? [];
        if (is_string($selected_roles)) {
            $selected_roles = array_values(array_filter(explode('|', $selected_roles)));
        }

        // Normalise stored date strings/timestamps → YYYY-MM-DD for date inputs
        $norm_date = function (string $key) use ($settings): string {
            $raw = $settings[$key] ?? '';
            if ($raw === '') { return ''; }
            $ts = is_numeric($raw) ? (int) $raw : @strtotime($raw);
            return ($ts && $ts !== -1) ? date('Y-m-d', $ts) : $raw;
        };

        $source_choices = [
            'entries' => lang('export_source_entries'),
            'members' => lang('export_source_members'),
            'grid'    => lang('export_source_grid'),
            'fluid'   => lang('export_source_fluid'),
            'sql'     => lang('export_source_sql'),
        ];

        $status_choices = [
            'open'   => lang('export_status_open'),
            'closed' => lang('export_status_closed'),
            'all'    => lang('export_status_all'),
        ];

        $format_choices = [
            'csv'  => 'CSV',
            'json' => 'JSON',
            'xlsx' => 'Excel (XLSX)',
            'xml'  => 'XML',
        ];

        $output_choices = [
            'download' => lang('export_output_download'),
            'local'    => lang('export_output_local'),
        ];

        $newline_choices = [
            '\n'   => 'LF (\n — Unix)',
            '\r\n' => 'CRLF (\r\n — Windows)',
            '\r'   => 'CR (\r — Classic Mac)',
        ];

        // ── Section 1 — Identity ─────────────────────────────────────────────

        $sections = [];

        $sections[] = [
            [
                'title'  => lang('export_field_name'),
                'desc'   => lang('export_field_name_desc'),
                'fields' => [
                    'name' => [
                        'type'     => 'text',
                        'value'    => $settings['name'] ?? '',
                        'required' => true,
                    ],
                ],
            ],
            [
                'title'  => lang('export_field_source'),
                'desc'   => lang('export_field_source_desc'),
                'fields' => [
                    'source' => [
                        'type'    => 'select',
                        'value'   => $source,
                        'choices' => $source_choices,
                    ],
                ],
            ],
        ];

        // ── Section 2 — Source options ───────────────────────────────────────
        //
        // Every source-specific row carries a `group` key.  EE's _shared/form
        // wraps each row in <div data-group="…"> so the JS in form.php can
        // show/hide whole rows by toggling visibility on those containers.
        //
        // Rows shared across multiple sources (channel, status, etc.) appear
        // once per source under a unique input name (src_{source}_{param}).

        $grid_field_id  = (int) ($settings['source:field'] ?? 0);
        $fluid_field_id = (int) ($settings['source:field'] ?? 0);

        $source_rows = [

            // ── entries ──────────────────────────────────────────────────────
            [
                'title'  => lang('export_field_channel'),
                'group'  => 'source_entries',
                'fields' => ['src_entries_channel' => ['type' => 'select', 'choices' => $channels, 'value' => $channel_id]],
            ],
            [
                'title'  => lang('export_field_status'),
                'group'  => 'source_entries',
                'fields' => ['src_entries_status' => ['type' => 'select', 'choices' => $status_choices, 'value' => $settings['source:status'] ?? 'open']],
            ],
            [
                'title'  => lang('export_field_author_id'),
                'group'  => 'source_entries',
                'fields' => ['src_entries_author_id' => ['type' => 'short-text', 'value' => $settings['source:author_id'] ?? '']],
            ],
            [
                'title'  => lang('export_field_entry_id'),
                'desc'   => lang('export_hint_pipe_sep'),
                'group'  => 'source_entries',
                'fields' => ['src_entries_entry_id' => ['type' => 'text', 'value' => $settings['source:entry_id'] ?? '']],
            ],
            [
                'title'  => lang('export_field_limit'),
                'group'  => 'source_entries',
                'fields' => ['src_entries_limit' => ['type' => 'short-text', 'value' => $settings['source:limit'] ?? '']],
            ],
            [
                'title'  => lang('export_field_offset'),
                'group'  => 'source_entries',
                'fields' => ['src_entries_offset' => ['type' => 'short-text', 'value' => $settings['source:offset'] ?? '0']],
            ],
            [
                'title'  => lang('export_field_chunk_size'),
                'group'  => 'source_entries',
                'fields' => ['src_entries_chunk_size' => ['type' => 'short-text', 'value' => $settings['source:chunk_size'] ?? '500']],
            ],
            [
                'title'  => lang('export_field_relationship_fields'),
                'desc'   => lang('export_hint_pipe_sep'),
                'group'  => 'source_entries',
                'fields' => ['src_entries_relationship_fields' => ['type' => 'text', 'value' => $settings['source:relationship_fields'] ?? 'title']],
            ],

            // ── members ──────────────────────────────────────────────────────
            [
                'title'  => lang('export_field_roles'),
                'group'  => 'source_members',
                'fields' => ['src_members_roles' => ['type' => 'checkbox', 'choices' => $roles, 'value' => $selected_roles]],
            ],
            [
                'title'  => lang('export_field_join_start'),
                'group'  => 'source_members',
                'fields' => ['src_members_join_start' => ['type' => 'text', 'value' => $norm_date('source:join_start'), 'placeholder' => 'YYYY-MM-DD', 'class' => 'export-date-field']],
            ],
            [
                'title'  => lang('export_field_join_end'),
                'group'  => 'source_members',
                'fields' => ['src_members_join_end' => ['type' => 'text', 'value' => $norm_date('source:join_end'), 'placeholder' => 'YYYY-MM-DD', 'class' => 'export-date-field']],
            ],
            [
                'title'  => lang('export_field_last_login_start'),
                'group'  => 'source_members',
                'fields' => ['src_members_last_login_start' => ['type' => 'text', 'value' => $norm_date('source:last_login_start'), 'placeholder' => 'YYYY-MM-DD', 'class' => 'export-date-field']],
            ],
            [
                'title'  => lang('export_field_last_login_end'),
                'group'  => 'source_members',
                'fields' => ['src_members_last_login_end' => ['type' => 'text', 'value' => $norm_date('source:last_login_end'), 'placeholder' => 'YYYY-MM-DD', 'class' => 'export-date-field']],
            ],
            [
                'title'  => lang('export_field_limit'),
                'group'  => 'source_members',
                'fields' => ['src_members_limit' => ['type' => 'short-text', 'value' => $settings['source:limit'] ?? '']],
            ],
            [
                'title'  => lang('export_field_offset'),
                'group'  => 'source_members',
                'fields' => ['src_members_offset' => ['type' => 'short-text', 'value' => $settings['source:offset'] ?? '0']],
            ],
            [
                'title'  => lang('export_field_chunk_size'),
                'group'  => 'source_members',
                'fields' => ['src_members_chunk_size' => ['type' => 'short-text', 'value' => $settings['source:chunk_size'] ?? '500']],
            ],

            // ── grid ─────────────────────────────────────────────────────────
            [
                'title'  => lang('export_field_channel'),
                'group'  => 'source_grid',
                'fields' => ['src_grid_channel' => ['type' => 'select', 'choices' => $channels, 'value' => $channel_id, 'data-field-type' => 'grid']],
            ],
            [
                'title'  => lang('export_field_field'),
                'group'  => 'source_grid',
                'fields' => ['src_grid_field' => ['type' => 'select', 'choices' => $grid_fields, 'value' => $grid_field_id]],
            ],
            [
                'title'  => lang('export_field_status'),
                'group'  => 'source_grid',
                'fields' => ['src_grid_status' => ['type' => 'select', 'choices' => $status_choices, 'value' => $settings['source:status'] ?? 'open']],
            ],
            [
                'title'  => lang('export_field_author_id'),
                'group'  => 'source_grid',
                'fields' => ['src_grid_author_id' => ['type' => 'short-text', 'value' => $settings['source:author_id'] ?? '']],
            ],
            [
                'title'  => lang('export_field_entry_id'),
                'desc'   => lang('export_hint_pipe_sep'),
                'group'  => 'source_grid',
                'fields' => ['src_grid_entry_id' => ['type' => 'text', 'value' => $settings['source:entry_id'] ?? '']],
            ],
            [
                'title'  => lang('export_field_limit'),
                'group'  => 'source_grid',
                'fields' => ['src_grid_limit' => ['type' => 'short-text', 'value' => $settings['source:limit'] ?? '']],
            ],
            [
                'title'  => lang('export_field_offset'),
                'group'  => 'source_grid',
                'fields' => ['src_grid_offset' => ['type' => 'short-text', 'value' => $settings['source:offset'] ?? '0']],
            ],
            [
                'title'  => lang('export_field_chunk_size'),
                'group'  => 'source_grid',
                'fields' => ['src_grid_chunk_size' => ['type' => 'short-text', 'value' => $settings['source:chunk_size'] ?? '500']],
            ],
            [
                'title'  => lang('export_field_relationship_fields'),
                'desc'   => lang('export_hint_pipe_sep'),
                'group'  => 'source_grid',
                'fields' => ['src_grid_relationship_fields' => ['type' => 'text', 'value' => $settings['source:relationship_fields'] ?? 'title']],
            ],

            // ── fluid ─────────────────────────────────────────────────────────
            [
                'title'  => lang('export_field_channel'),
                'group'  => 'source_fluid',
                'fields' => ['src_fluid_channel' => ['type' => 'select', 'choices' => $channels, 'value' => $channel_id, 'data-field-type' => 'fluid_field']],
            ],
            [
                'title'  => lang('export_field_field'),
                'group'  => 'source_fluid',
                'fields' => ['src_fluid_field' => ['type' => 'select', 'choices' => $fluid_fields, 'value' => $fluid_field_id]],
            ],
            [
                'title'  => lang('export_field_status'),
                'group'  => 'source_fluid',
                'fields' => ['src_fluid_status' => ['type' => 'select', 'choices' => $status_choices, 'value' => $settings['source:status'] ?? 'open']],
            ],
            [
                'title'  => lang('export_field_author_id'),
                'group'  => 'source_fluid',
                'fields' => ['src_fluid_author_id' => ['type' => 'short-text', 'value' => $settings['source:author_id'] ?? '']],
            ],
            [
                'title'  => lang('export_field_entry_id'),
                'desc'   => lang('export_hint_pipe_sep'),
                'group'  => 'source_fluid',
                'fields' => ['src_fluid_entry_id' => ['type' => 'text', 'value' => $settings['source:entry_id'] ?? '']],
            ],
            [
                'title'  => lang('export_field_limit'),
                'group'  => 'source_fluid',
                'fields' => ['src_fluid_limit' => ['type' => 'short-text', 'value' => $settings['source:limit'] ?? '']],
            ],
            [
                'title'  => lang('export_field_offset'),
                'group'  => 'source_fluid',
                'fields' => ['src_fluid_offset' => ['type' => 'short-text', 'value' => $settings['source:offset'] ?? '0']],
            ],
            [
                'title'  => lang('export_field_chunk_size'),
                'group'  => 'source_fluid',
                'fields' => ['src_fluid_chunk_size' => ['type' => 'short-text', 'value' => $settings['source:chunk_size'] ?? '500']],
            ],
            [
                'title'  => lang('export_field_relationship_fields'),
                'desc'   => lang('export_hint_pipe_sep'),
                'group'  => 'source_fluid',
                'fields' => ['src_fluid_relationship_fields' => ['type' => 'text', 'value' => $settings['source:relationship_fields'] ?? 'title']],
            ],

            // ── sql ──────────────────────────────────────────────────────────
            [
                'title'  => lang('export_field_sql'),
                'group'  => 'source_sql',
                'fields' => [
                    'src_sql_sql' => [
                        'type'  => 'textarea',
                        'value' => $settings['source:sql'] ?? '',
                    ],
                ],
            ],
        ];

        $sections[] = $source_rows;

        // ── Section 3 — Column selection ─────────────────────────────────────
        // The picker itself needs AJAX-loaded checkboxes so it stays as html,
        // but it is a single contained block rather than the whole source section.

        $col_mode       = 'all';
        $stored_fields  = $settings['fields']  ?? [];
        $stored_exclude = $settings['exclude'] ?? [];
        if (! empty($stored_fields))  { $col_mode = 'whitelist'; }
        elseif (! empty($stored_exclude)) { $col_mode = 'blacklist'; }

        $fields_val  = is_array($stored_fields)  ? implode('|', $stored_fields)  : (string) $stored_fields;
        $exclude_val = is_array($stored_exclude) ? implode('|', $stored_exclude) : (string) $stored_exclude;

        $col_html  = '<div class="export-col-mode">';
        foreach (['all' => lang('export_col_all'), 'whitelist' => lang('export_col_whitelist'), 'blacklist' => lang('export_col_blacklist')] as $val => $lbl) {
            $col_html .= '<label style="margin-right:1em"><input type="radio" name="col_mode" value="' . $val . '"' . ($col_mode === $val ? ' checked' : '') . '> ' . $lbl . '</label>';
        }
        $col_html .= '</div>';
        $col_html .= '<div class="export-col-picker" style="margin-top:.75em;' . ($col_mode === 'all' ? 'display:none' : '') . '">';
        $col_html .= '<div class="export-col-checkboxes"></div>';
        $col_html .= '<input type="hidden" name="fields"  id="export_fields_val"  value="' . htmlspecialchars($fields_val) . '">';
        $col_html .= '<input type="hidden" name="exclude" id="export_exclude_val" value="' . htmlspecialchars($exclude_val) . '">';
        $col_html .= '</div>';

        $sections[] = [
            [
                'title'  => lang('export_section_columns'),
                'desc'   => lang('export_section_columns_desc'),
                'fields' => ['column_selection' => ['type' => 'html', 'content' => $col_html]],
            ],
        ];

        // ── Section 4 — Format ────────────────────────────────────────────────

        $format = $settings['format'] ?? 'csv';

        $format_rows = [
            // format selector (always visible)
            [
                'title'  => lang('export_section_format'),
                'fields' => ['format' => ['type' => 'select', 'value' => $format, 'choices' => $format_choices]],
            ],

            // CSV options
            [
                'title'  => lang('export_format_separator'),
                'desc'   => lang('export_format_separator_desc'),
                'group'  => 'format_csv',
                'fields' => ['fmt_separator' => ['type' => 'short-text', 'value' => $settings['format:separator'] ?? ',', 'maxlength' => 1]],
            ],
            [
                'title'  => lang('export_format_enclosure'),
                'desc'   => lang('export_format_enclosure_desc'),
                'group'  => 'format_csv',
                'fields' => ['fmt_enclosure' => ['type' => 'short-text', 'value' => $settings['format:enclosure'] ?? '"', 'maxlength' => 1]],
            ],
            [
                'title'  => lang('export_format_escape'),
                'desc'   => lang('export_format_escape_desc'),
                'group'  => 'format_csv',
                'fields' => ['fmt_escape' => ['type' => 'short-text', 'value' => $settings['format:escape'] ?? '\\', 'maxlength' => 1]],
            ],
            [
                'title'  => lang('export_format_newline'),
                'desc'   => lang('export_format_newline_desc'),
                'group'  => 'format_csv',
                'fields' => ['fmt_newline' => ['type' => 'select', 'value' => $settings['format:newline'] ?? '\n', 'choices' => $newline_choices]],
            ],

            // XLSX options
            [
                'title'  => lang('export_format_bold_cols'),
                'desc'   => lang('export_format_bold_cols_desc'),
                'group'  => 'format_xlsx',
                'fields' => ['fmt_bold_cols' => ['type' => 'toggle', 'value' => $settings['format:bold_cols'] ?? 'y']],
            ],
            [
                'title'  => lang('export_format_sheet_name'),
                'desc'   => lang('export_format_sheet_name_desc'),
                'group'  => 'format_xlsx',
                'fields' => ['fmt_sheet_name' => ['type' => 'text', 'value' => $settings['format:sheet_name'] ?? '']],
            ],

            // XML options
            [
                'title'  => lang('export_format_root_name'),
                'desc'   => lang('export_format_root_name_desc'),
                'group'  => 'format_xml',
                'fields' => ['fmt_root_name' => ['type' => 'text', 'value' => $settings['format:root_name'] ?? 'export']],
            ],
            [
                'title'  => lang('export_format_branch_name'),
                'desc'   => lang('export_format_branch_name_desc'),
                'group'  => 'format_xml',
                'fields' => ['fmt_branch_name' => ['type' => 'text', 'value' => $settings['format:branch_name'] ?? 'row']],
            ],
        ];

        $sections[] = $format_rows;

        // ── Section 5 — Output ────────────────────────────────────────────────

        $output   = $settings['output']          ?? 'download';
        $filename = $settings['output:filename'] ?? '';
        $path     = $settings['output:path']     ?? '';

        $sections[] = [
            [
                'title'  => lang('export_section_output'),
                'fields' => ['output' => ['type' => 'select', 'value' => $output, 'choices' => $output_choices]],
            ],
            [
                'title'    => lang('export_field_filename'),
                'required' => true,
                'fields'   => ['output_filename' => ['type' => 'text', 'value' => $filename, 'required' => true]],
            ],
            [
                'title'  => lang('export_field_path'),
                'desc'   => lang('export_field_path_desc'),
                'group'  => 'output_local',
                'fields' => ['output_path' => ['type' => 'text', 'value' => $path]],
            ],
        ];

        // ── Section 6 — Modifiers ─────────────────────────────────────────────
        // Dynamic add/remove rows require raw HTML; no equivalent in _shared/form.

        $modifiers = [];
        foreach ($settings as $key => $value) {
            if (str_starts_with($key, 'modify:')) {
                $modifiers[] = ['column' => substr($key, 7), 'chain' => $value];
            }
        }

        $mod_rows = '';
        foreach ($modifiers as $i => $mod) {
            $mod_rows .= '<tr class="export-modifier-row">'
                . '<td><input type="text" name="modify[' . $i . '][column]" class="form-control" value="' . htmlspecialchars($mod['column']) . '" placeholder="column_name"></td>'
                . '<td><input type="text" name="modify[' . $i . '][chain]"  class="form-control" value="' . htmlspecialchars($mod['chain'])  . '" placeholder="ee_date[%Y-%m-%d]|uc_first"></td>'
                . '<td><a href="#" class="export-remove-modifier button button--small button--default">' . lang('export_remove') . '</a></td>'
                . '</tr>';
        }

        $mod_html = '<table class="mainTable"><thead><tr>'
            . '<th>' . lang('export_modifier_column') . '</th>'
            . '<th>' . lang('export_modifier_chain')  . '</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody id="export-modifier-rows">' . $mod_rows . '</tbody>'
            . '</table>'
            . '<a href="#" class="button button--default export-add-modifier" style="margin-top:.5em" data-row-count="' . count($modifiers) . '">'
            . lang('export_add_modifier') . '</a>';

        $sections[] = [
            [
                'title'  => lang('export_section_modifiers'),
                'desc'   => lang('export_section_modifiers_desc'),
                'fields' => ['modifiers_html' => ['type' => 'html', 'content' => $mod_html]],
            ],
        ];

        return $sections;
    }

    // ── POST → settings conversion ────────────────────────────────────────────

    /**
     * Convert raw POST data from the Create/Edit form into a settings array
     * ready to be stored as JSON in ExportConfiguration::$settings.
     *
     * @param array  $post   Raw $_POST
     * @param string $source The selected source key
     *
     * @return array
     */
    public function postToSettings(array $post, string $source): array
    {
        $settings = [];

        // Source-specific params — strip the `src_{source}_` prefix
        $prefix = 'src_' . $source . '_';
        foreach ($post as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $param_key = 'source:' . substr($key, strlen($prefix));
                $settings[$param_key] = is_array($value) ? implode('|', $value) : $value;
            }
        }

        // Column selection
        $col_mode = $post['col_mode'] ?? 'all';
        if ($col_mode === 'whitelist') {
            $raw = trim($post['fields'] ?? '');
            $settings['fields']  = $raw ? array_filter(explode('|', $raw)) : [];
            $settings['exclude'] = [];
        } elseif ($col_mode === 'blacklist') {
            $raw = trim($post['exclude'] ?? '');
            $settings['fields']  = [];
            $settings['exclude'] = $raw ? array_filter(explode('|', $raw)) : [];
        } else {
            $settings['fields']  = [];
            $settings['exclude'] = [];
        }

        // Format
        $settings['format'] = $post['format'] ?? 'csv';

        // Single-char fields stored verbatim; all others trimmed
        $char_fields   = ['separator', 'enclosure', 'escape'];
        $format_keys   = ['separator', 'enclosure', 'escape', 'newline', 'bold_cols', 'sheet_name', 'root_name', 'branch_name'];
        foreach ($format_keys as $fk) {
            $field_name = 'fmt_' . $fk;
            if (! isset($post[$field_name])) { continue; }
            $val = in_array($fk, $char_fields, true) ? $post[$field_name] : trim($post[$field_name]);
            if ($val !== '') {
                $settings['format:' . $fk] = $val;
            }
        }

        // bold_cols is a checkbox — absent from POST means unchecked
        if ($settings['format'] === 'xlsx' && ! isset($post['fmt_bold_cols'])) {
            $settings['format:bold_cols'] = 'n';
        }

        // Output
        $settings['output']          = $post['output']          ?? 'download';
        $settings['output:filename'] = $post['output_filename'] ?? '';
        if (!empty($post['output_path'])) {
            $settings['output:path'] = $post['output_path'];
        }

        // Modifiers
        $modifiers = $post['modify'] ?? [];
        foreach ($modifiers as $row) {
            $col   = trim($row['column'] ?? '');
            $chain = trim($row['chain']  ?? '');
            if ($col !== '' && $chain !== '') {
                $settings['modify:' . $col] = $chain;
            }
        }

        return $settings;
    }
}
