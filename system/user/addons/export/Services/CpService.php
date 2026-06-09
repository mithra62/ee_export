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
                ->first();

            if (! $channel) {
                return [];
            }

            // getAllCustomFields() merges fields assigned directly to the channel
            // AND fields belonging to any attached field groups — unlike querying
            // the CustomFields relationship alone, which misses group-assigned fields.
            $all_fields = $channel->getAllCustomFields();

            if (! $all_fields || ! count($all_fields)) {
                return [];
            }

            $list = [];
            foreach ($all_fields as $field) {
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

    // ── Form builder ──────────────────────────────────────────────────────────

    /**
     * Build and return a configured CP/Form object for the Create / Edit form.
     *
     * The caller (Create/Edit route) chains form-level metadata onto the returned
     * object (setCpPageTitle, setBaseUrl, setSaveBtnText, setSaveBtnTextWorking)
     * then calls toArray() to pass to setView().
     *
     * Group names are lang keys — _shared/form/section.php passes them through
     * lang() to render <h2> section headings.
     *
     * group_toggle on select fields is set via ->set('group_toggle', array) rather
     * than ->setGroupToggle(string) because the view expects an array (json_encode'd
     * by field.php into data-group-toggle), while the typed method only accepts string.
     *
     * group on individual Set (fieldset row) objects is set via $set->set('group', '…');
     * Set::toArray() iterates the full prototype dict including dynamically added keys,
     * so the key propagates correctly into the view's $setting array.
     *
     * @param array  $settings  Decoded settings from ExportConfiguration::getSettings()
     * @param string $source    Currently selected source key
     *
     * @return \ExpressionEngine\Library\CP\Form
     */
    public function buildForm(array $settings, string $source = 'entries'): \ExpressionEngine\Library\CP\Form
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

        $grid_field_id  = (int) ($settings['source:field'] ?? 0);
        $fluid_field_id = (int) ($settings['source:field'] ?? 0);

        $format = $settings['format'] ?? 'csv';
        $output = $settings['output'] ?? 'download';

        // ── Build Form object ─────────────────────────────────────────────────

        $form = ee('CP/Form');

        // Load addon JS and expose the AJAX endpoint URL to JS scope
        ee()->cp->load_package_js('export');
        ee()->javascript->set_global(
            'Export.ajax_url',
            ee('CP/URL')->make('addons/settings/export/ajax')->compile()
        );

        // ── Section 1 — Identity ─────────────────────────────────────────────

        $identity = $form->getGroup('export_section_identity');

        $identity->getFieldSet('export_field_name')
            ->setDesc('export_field_name_desc')
            ->getField('name', 'text')
                ->setValue($settings['name'] ?? '')
                ->setRequired(true);

        $identity->getFieldSet('export_field_source')
            ->setDesc('export_field_source_desc')
            ->getField('source', 'select')
                ->setChoices($source_choices)
                ->setValue($source)
                ->set('group_toggle', [
                    'entries' => 'source_entries',
                    'members' => 'source_members',
                    'grid'    => 'source_grid',
                    'fluid'   => 'source_fluid',
                    'sql'     => 'source_sql',
                ]);

        // ── Section 2 — Source options ────────────────────────────────────────

        $src = $form->getGroup('export_section_source_params');

        // entries ─────────────────────────────────────────────────────────────
        $src->getFieldSet('export_field_channel_entries')
            ->set('group', 'source_entries')
            ->setTitle('export_field_channel')
            ->getField('src_entries_channel', 'select')
                ->setChoices($channels)->setValue($channel_id);

        $src->getFieldSet('export_field_status_entries')
            ->set('group', 'source_entries')
            ->setTitle('export_field_status')
            ->getField('src_entries_status', 'select')
                ->setChoices($status_choices)->setValue($settings['source:status'] ?? 'open');

        $src->getFieldSet('export_field_author_id_entries')
            ->set('group', 'source_entries')
            ->setTitle('export_field_author_id')
            ->getField('src_entries_author_id', 'text')
                ->setValue($settings['source:author_id'] ?? '');

        $src->getFieldSet('export_field_entry_id_entries')
            ->set('group', 'source_entries')
            ->setTitle('export_field_entry_id')
            ->setDesc('export_hint_pipe_sep')
            ->getField('src_entries_entry_id', 'text')
                ->setValue($settings['source:entry_id'] ?? '');

        $src->getFieldSet('export_field_limit_entries')
            ->set('group', 'source_entries')
            ->setTitle('export_field_limit')
            ->getField('src_entries_limit', 'text')
                ->setValue($settings['source:limit'] ?? '');

        $src->getFieldSet('export_field_offset_entries')
            ->set('group', 'source_entries')
            ->setTitle('export_field_offset')
            ->getField('src_entries_offset', 'text')
                ->setValue($settings['source:offset'] ?? '0');

        $src->getFieldSet('export_field_chunk_size_entries')
            ->set('group', 'source_entries')
            ->setTitle('export_field_chunk_size')
            ->getField('src_entries_chunk_size', 'text')
                ->setValue($settings['source:chunk_size'] ?? '500');

        $src->getFieldSet('export_field_relationship_fields_entries')
            ->set('group', 'source_entries')
            ->setTitle('export_field_relationship_fields')
            ->setDesc('export_hint_pipe_sep')
            ->getField('src_entries_relationship_fields', 'text')
                ->setValue($settings['source:relationship_fields'] ?? 'title');

        // members ─────────────────────────────────────────────────────────────
        $src->getFieldSet('export_field_roles')
            ->set('group', 'source_members')
            ->getField('src_members_roles', 'checkbox')
                ->setChoices($roles)->setValue($selected_roles);

        $src->getFieldSet('export_field_join_start')
            ->set('group', 'source_members')
            ->getField('src_members_join_start', 'html')
                ->setContent('<input type="date" name="src_members_join_start" value="' . htmlspecialchars($norm_date('source:join_start')) . '" class="form-control">');

        $src->getFieldSet('export_field_join_end')
            ->set('group', 'source_members')
            ->getField('src_members_join_end', 'html')
                ->setContent('<input type="date" name="src_members_join_end" value="' . htmlspecialchars($norm_date('source:join_end')) . '" class="form-control">');

        $src->getFieldSet('export_field_last_login_start')
            ->set('group', 'source_members')
            ->getField('src_members_last_login_start', 'html')
                ->setContent('<input type="date" name="src_members_last_login_start" value="' . htmlspecialchars($norm_date('source:last_login_start')) . '" class="form-control">');

        $src->getFieldSet('export_field_last_login_end')
            ->set('group', 'source_members')
            ->getField('src_members_last_login_end', 'html')
                ->setContent('<input type="date" name="src_members_last_login_end" value="' . htmlspecialchars($norm_date('source:last_login_end')) . '" class="form-control">');

        $src->getFieldSet('export_field_limit_members')
            ->set('group', 'source_members')
            ->setTitle('export_field_limit')
            ->getField('src_members_limit', 'text')
                ->setValue($settings['source:limit'] ?? '');

        $src->getFieldSet('export_field_offset_members')
            ->set('group', 'source_members')
            ->setTitle('export_field_offset')
            ->getField('src_members_offset', 'text')
                ->setValue($settings['source:offset'] ?? '0');

        $src->getFieldSet('export_field_chunk_size_members')
            ->set('group', 'source_members')
            ->setTitle('export_field_chunk_size')
            ->getField('src_members_chunk_size', 'text')
                ->setValue($settings['source:chunk_size'] ?? '500');

        // grid ────────────────────────────────────────────────────────────────
        $src->getFieldSet('export_field_channel_grid')
            ->set('group', 'source_grid')
            ->setTitle('export_field_channel')
            ->getField('src_grid_channel', 'select')
                ->setChoices($channels)->setValue($channel_id);

        $src->getFieldSet('export_field_field_grid')
            ->set('group', 'source_grid')
            ->setTitle('export_field_field')
            ->getField('src_grid_field', 'select')
                ->setChoices($grid_fields)->setValue($grid_field_id);

        $src->getFieldSet('export_field_status_grid')
            ->set('group', 'source_grid')
            ->setTitle('export_field_status')
            ->getField('src_grid_status', 'select')
                ->setChoices($status_choices)->setValue($settings['source:status'] ?? 'open');

        $src->getFieldSet('export_field_author_id_grid')
            ->set('group', 'source_grid')
            ->setTitle('export_field_author_id')
            ->getField('src_grid_author_id', 'text')
                ->setValue($settings['source:author_id'] ?? '');

        $src->getFieldSet('export_field_entry_id_grid')
            ->set('group', 'source_grid')
            ->setTitle('export_field_entry_id')
            ->setDesc('export_hint_pipe_sep')
            ->getField('src_grid_entry_id', 'text')
                ->setValue($settings['source:entry_id'] ?? '');

        $src->getFieldSet('export_field_limit_grid')
            ->set('group', 'source_grid')
            ->setTitle('export_field_limit')
            ->getField('src_grid_limit', 'text')
                ->setValue($settings['source:limit'] ?? '');

        $src->getFieldSet('export_field_offset_grid')
            ->set('group', 'source_grid')
            ->setTitle('export_field_offset')
            ->getField('src_grid_offset', 'text')
                ->setValue($settings['source:offset'] ?? '0');

        $src->getFieldSet('export_field_chunk_size_grid')
            ->set('group', 'source_grid')
            ->setTitle('export_field_chunk_size')
            ->getField('src_grid_chunk_size', 'text')
                ->setValue($settings['source:chunk_size'] ?? '500');

        $src->getFieldSet('export_field_relationship_fields_grid')
            ->set('group', 'source_grid')
            ->setTitle('export_field_relationship_fields')
            ->setDesc('export_hint_pipe_sep')
            ->getField('src_grid_relationship_fields', 'text')
                ->setValue($settings['source:relationship_fields'] ?? 'title');

        // fluid ───────────────────────────────────────────────────────────────
        $src->getFieldSet('export_field_channel_fluid')
            ->set('group', 'source_fluid')
            ->setTitle('export_field_channel')
            ->getField('src_fluid_channel', 'select')
                ->setChoices($channels)->setValue($channel_id);

        $src->getFieldSet('export_field_field_fluid')
            ->set('group', 'source_fluid')
            ->setTitle('export_field_field')
            ->getField('src_fluid_field', 'select')
                ->setChoices($fluid_fields)->setValue($fluid_field_id);

        $src->getFieldSet('export_field_status_fluid')
            ->set('group', 'source_fluid')
            ->setTitle('export_field_status')
            ->getField('src_fluid_status', 'select')
                ->setChoices($status_choices)->setValue($settings['source:status'] ?? 'open');

        $src->getFieldSet('export_field_author_id_fluid')
            ->set('group', 'source_fluid')
            ->setTitle('export_field_author_id')
            ->getField('src_fluid_author_id', 'text')
                ->setValue($settings['source:author_id'] ?? '');

        $src->getFieldSet('export_field_entry_id_fluid')
            ->set('group', 'source_fluid')
            ->setTitle('export_field_entry_id')
            ->setDesc('export_hint_pipe_sep')
            ->getField('src_fluid_entry_id', 'text')
                ->setValue($settings['source:entry_id'] ?? '');

        $src->getFieldSet('export_field_limit_fluid')
            ->set('group', 'source_fluid')
            ->setTitle('export_field_limit')
            ->getField('src_fluid_limit', 'text')
                ->setValue($settings['source:limit'] ?? '');

        $src->getFieldSet('export_field_offset_fluid')
            ->set('group', 'source_fluid')
            ->setTitle('export_field_offset')
            ->getField('src_fluid_offset', 'text')
                ->setValue($settings['source:offset'] ?? '0');

        $src->getFieldSet('export_field_chunk_size_fluid')
            ->set('group', 'source_fluid')
            ->setTitle('export_field_chunk_size')
            ->getField('src_fluid_chunk_size', 'text')
                ->setValue($settings['source:chunk_size'] ?? '500');

        $src->getFieldSet('export_field_relationship_fields_fluid')
            ->set('group', 'source_fluid')
            ->setTitle('export_field_relationship_fields')
            ->setDesc('export_hint_pipe_sep')
            ->getField('src_fluid_relationship_fields', 'text')
                ->setValue($settings['source:relationship_fields'] ?? 'title');

        // sql ─────────────────────────────────────────────────────────────────
        $src->getFieldSet('export_field_sql')
            ->set('group', 'source_sql')
            ->getField('src_sql_sql', 'textarea')
                ->setValue($settings['source:sql'] ?? '');

        // ── Section 3 — Column selection ─────────────────────────────────────

        $col_mode       = 'all';
        $stored_fields  = $settings['fields']  ?? [];
        $stored_exclude = $settings['exclude'] ?? [];
        if (! empty($stored_fields))      { $col_mode = 'whitelist'; }
        elseif (! empty($stored_exclude)) { $col_mode = 'blacklist'; }

        $fields_val  = is_array($stored_fields)  ? implode('|', $stored_fields)  : (string) $stored_fields;
        $exclude_val = is_array($stored_exclude) ? implode('|', $stored_exclude) : (string) $stored_exclude;

        $col_html = '<div class="export-col-mode">';
        foreach (['all' => lang('export_col_all'), 'whitelist' => lang('export_col_whitelist'), 'blacklist' => lang('export_col_blacklist')] as $val => $lbl) {
            $col_html .= '<label style="margin-right:1em"><input type="radio" name="col_mode" value="' . $val . '"' . ($col_mode === $val ? ' checked' : '') . '> ' . $lbl . '</label>';
        }
        $col_html .= '</div>';
        $col_html .= '<div class="export-col-picker"' . ($col_mode === 'all' ? ' style="display:none"' : '') . '>';
        $col_html .= '<div class="export-col-checkboxes"></div>';
        $col_html .= '</div>';
        $col_html .= '<input type="hidden" name="fields"  id="export_fields_val"  value="' . htmlspecialchars($fields_val) . '">';
        $col_html .= '<input type="hidden" name="exclude" id="export_exclude_val" value="' . htmlspecialchars($exclude_val) . '">';

        $form->getGroup('export_section_columns')
            ->getFieldSet('export_section_columns_desc')
                ->setTitle('export_section_columns')
                ->setDesc('export_section_columns_desc')
                ->getField('column_selection', 'html')
                    ->setContent($col_html);

        // ── Section 4 — Format ────────────────────────────────────────────────

        $fmt = $form->getGroup('export_section_format_options');

        $fmt->getFieldSet('export_section_format')
            ->getField('format', 'select')
                ->setChoices($format_choices)
                ->setValue($format)
                ->set('group_toggle', [
                    'csv'  => 'format_csv',
                    'xlsx' => 'format_xlsx',
                    'xml'  => 'format_xml',
                ]);

        $fmt->getFieldSet('export_format_separator')
            ->set('group', 'format_csv')
            ->setDesc('export_format_separator_desc')
            ->getField('fmt_separator', 'text')
                ->setValue($settings['format:separator'] ?? ',')
                ->setMaxlength(1);

        $fmt->getFieldSet('export_format_enclosure')
            ->set('group', 'format_csv')
            ->setDesc('export_format_enclosure_desc')
            ->getField('fmt_enclosure', 'text')
                ->setValue($settings['format:enclosure'] ?? '"')
                ->setMaxlength(1);

        $fmt->getFieldSet('export_format_escape')
            ->set('group', 'format_csv')
            ->setDesc('export_format_escape_desc')
            ->getField('fmt_escape', 'text')
                ->setValue($settings['format:escape'] ?? '\\')
                ->setMaxlength(1);

        $fmt->getFieldSet('export_format_newline')
            ->set('group', 'format_csv')
            ->setDesc('export_format_newline_desc')
            ->getField('fmt_newline', 'select')
                ->setChoices($newline_choices)
                ->setValue($settings['format:newline'] ?? '\n');

        $fmt->getFieldSet('export_format_bold_cols')
            ->set('group', 'format_xlsx')
            ->setDesc('export_format_bold_cols_desc')
            ->getField('fmt_bold_cols', 'toggle')
                ->setValue($settings['format:bold_cols'] ?? 'y');

        $fmt->getFieldSet('export_format_sheet_name')
            ->set('group', 'format_xlsx')
            ->setDesc('export_format_sheet_name_desc')
            ->getField('fmt_sheet_name', 'text')
                ->setValue($settings['format:sheet_name'] ?? '');

        $fmt->getFieldSet('export_format_root_name')
            ->set('group', 'format_xml')
            ->setDesc('export_format_root_name_desc')
            ->getField('fmt_root_name', 'text')
                ->setValue($settings['format:root_name'] ?? 'export');

        $fmt->getFieldSet('export_format_branch_name')
            ->set('group', 'format_xml')
            ->setDesc('export_format_branch_name_desc')
            ->getField('fmt_branch_name', 'text')
                ->setValue($settings['format:branch_name'] ?? 'row');

        // ── Section 5 — Output ────────────────────────────────────────────────

        $out = $form->getGroup('export_section_output');

        $out->getFieldSet('export_section_output')
            ->setTitle('export_section_output')
            ->getField('output', 'select')
                ->setChoices($output_choices)
                ->setValue($output)
                ->set('group_toggle', ['local' => 'output_local']);

        $out->getFieldSet('export_field_filename')
            ->getField('output_filename', 'text')
                ->setValue($settings['output:filename'] ?? '')
                ->setRequired(true);

        $out->getFieldSet('export_field_path')
            ->set('group', 'output_local')
            ->setDesc('export_field_path_desc')
            ->getField('output_path', 'text')
                ->setValue($settings['output:path'] ?? '');

        // ── Section 6 — Modifiers (MiniGrid) ─────────────────────────────────

        $mg = ee('CP/MiniGridInput', ['field_name' => 'modify']);
        $mg->loadAssets();
        $mg->setColumns([
            'column' => ['label' => lang('export_modifier_column')],
            'chain'  => ['label' => lang('export_modifier_chain')],
        ]);
        $mg->setNoResultsText('no_rows_created', 'add_a_row');
        $mg->setBlankRow([
            ['html' => form_input('column', '', 'class="form-control" placeholder="column_name"')],
            ['html' => form_input('chain',  '', 'class="form-control" placeholder="ee_date[%Y-%m-%d]|uc_first"')],
        ]);
        $mg->setData([]);

        $modifiers = [];
        foreach ($settings as $key => $value) {
            if (str_starts_with($key, 'modify:')) {
                $modifiers[] = ['column' => substr($key, 7), 'chain' => $value];
            }
        }
        if ($modifiers) {
            $rows = [];
            foreach ($modifiers as $i => $mod) {
                $rows[] = [
                    'attrs'   => ['row_id' => $i + 1],
                    'columns' => [
                        ['html' => form_input('column', $mod['column'], 'class="form-control"')],
                        ['html' => form_input('chain',  $mod['chain'],  'class="form-control"')],
                    ],
                ];
            }
            $mg->setData($rows);
        }

        $form->getGroup('export_section_modifiers')
            ->getFieldSet('export_section_modifiers_desc')
                ->setTitle('export_section_modifiers')
                ->setDesc('export_section_modifiers_desc')
                ->getField('modifiers_html', 'html')
                    ->setContent(ee('View')->make('ee:_shared/form/mini_grid')->render($mg->viewData()));

        return $form;
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

        // Modifiers — MiniGridInput posts as modify[rows][row_id_N | new_row_N][col]
        // The hidden blank-row template (new_row_0) is always present with empty
        // values; skipping empty column/chain naturally filters it out.
        $modifier_rows = $post['modify']['rows'] ?? [];
        foreach ($modifier_rows as $row) {
            $col   = trim($row['column'] ?? '');
            $chain = trim($row['chain']  ?? '');
            if ($col !== '' && $chain !== '') {
                $settings['modify:' . $col] = $chain;
            }
        }

        return $settings;
    }
}
