<?php

namespace Mithra62\Export\Forms;

/**
 * Create / Edit form for a saved Export configuration.
 *
 * Builds the full six-section CP/Form and returns it as a plain array via
 * generate() so callers can stitch in form-level metadata (cp_page_title,
 * base_url, save_btn_text, save_btn_text_working) before passing to setView().
 *
 * This is structurally identical to DeleteExport — both extend AbstractExportForm,
 * both implement generate(): array. The Create and Edit routes follow the exact
 * same pattern as Delete: instantiate, call generate(), merge meta, setView().
 *
 * Section overview
 * ────────────────
 *   1. Identity        — name, source selector (group_toggle → sections 2a–2e)
 *   2. Source options  — entries / members / grid / fluid / sql (toggled)
 *   3. Column picker   — all / whitelist / blacklist (AJAX-driven checkboxes)
 *   4. Format          — csv / xlsx / xml options (group_toggle)
 *   5. Output          — download / local options (group_toggle)
 *   6. Modifiers       — MiniGrid for per-column modifier chains
 */
class CreateEditExport extends AbstractExportForm
{
    /** @var array Decoded settings from ExportConfiguration::getSettings() */
    protected array $settings;

    /** @var string Currently selected source key */
    protected string $source;

    public function __construct(array $settings = [], string $source = 'entries')
    {
        $this->settings = $settings;
        $this->source   = $source;
    }

    /**
     * Build and return the full Create/Edit form as a vars array.
     *
     * group_toggle on select fields is set via ->set('group_toggle', array) rather
     * than ->setGroupToggle(string) because field.php expects an array it can
     * json_encode into data-group-toggle, while the typed method accepts only string.
     *
     * group on individual FieldSet rows is set via $set->set('group', '…');
     * Set::toArray() propagates arbitrary keys set this way into the view's
     * $setting array so fieldset.php can render data-group="…" correctly.
     */
    public function generate(): array
    {
        $cp      = ee('export:CpService');
        $settings = $this->settings;
        $source   = $this->source;

        $channels   = $cp->getChannelList();
        $roles      = $cp->getMemberRoles();
        // Entries/Grid/Fluid each read their channel from different settings keys.
        // Grid/Fluid use source-specific keys so switching source in the editor
        // never cross-populates the wrong channel. Fall back to source:channel for
        // configs saved before L-9 was resolved.
        $channel_id       = (int) ($settings['source:channel'] ?? 0); // entries
        $grid_channel_id  = (int) ($settings['grid:channel']   ?? $settings['source:channel'] ?? 0);
        $fluid_channel_id = (int) ($settings['fluid:channel']  ?? $settings['source:channel'] ?? 0);

        $grid_fields  = $grid_channel_id  ? $cp->getChannelFields($grid_channel_id,  'grid')        : [];
        $fluid_fields = $fluid_channel_id ? $cp->getChannelFields($fluid_channel_id, 'fluid_field') : [];

        $selected_roles = $settings['source:roles'] ?? [];
        if (is_string($selected_roles)) {
            $selected_roles = array_values(array_filter(explode('|', $selected_roles)));
        }

        $selected_allowed_roles = $settings['allowed_roles'] ?? [];
        if (is_string($selected_allowed_roles) && $selected_allowed_roles !== '') {
            $selected_allowed_roles = array_values(array_filter(array_map('intval', explode('|', $selected_allowed_roles))));
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

        $grid_field_id  = (int) ($settings['grid:field']  ?? $settings['source:field'] ?? 0);
        $fluid_field_id = (int) ($settings['fluid:field'] ?? $settings['source:field'] ?? 0);

        $format = $settings['format'] ?? 'csv';
        $output = $settings['output'] ?? 'download';

        // ── Build Form object ─────────────────────────────────────────────────

        $form = $this->makeForm();

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

        $identity->getFieldSet('export_field_allowed_roles')
            ->setDesc('export_field_allowed_roles_desc')
            ->getField('allowed_roles', 'checkbox')
                ->setChoices($roles)
                ->setValue($selected_allowed_roles);

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
                ->setChoices($channels)->setValue($grid_channel_id);

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
            ->setDesc('export_field_limit_grid_desc')
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
                ->setChoices($channels)->setValue($fluid_channel_id);

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
            ->setDesc('export_field_limit_fluid_desc')
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

        return $form->toArray();
    }
}
