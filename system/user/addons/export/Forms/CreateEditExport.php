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
 *   2. Source options  — one registered source per fieldset group (toggled)
 *   3. Column picker   — all / whitelist / blacklist (AJAX-driven checkboxes)
 *   4. Format          — one registered format per fieldset group (toggled)
 *   5. Output          — one registered output per fieldset group (toggled)
 *   6. Modifiers       — MiniGrid for per-column modifier chains
 *
 * Sections 2, 4, and 5's per-plugin fields are NOT hardcoded here. Each
 * registered source/format/output (built-in or third-party) declares its own
 * CP fields via AbstractPlugin::getCpFields(), and AbstractExportForm's
 * renderPluginCpFields() renders every registered key through one shared code
 * path. See EXTENDING.md "CP Form Fields" for the contract.
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
        $this->source = $source;
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
        $cp = ee('export:CpService');
        $settings = $this->settings;
        $source = $this->source;

        $roles = $cp->getMemberRoles();

        $selected_allowed_roles = $settings['allowed_roles'] ?? [];
        if (is_string($selected_allowed_roles) && $selected_allowed_roles !== '') {
            $selected_allowed_roles = array_values(array_filter(array_map('intval', explode('|', $selected_allowed_roles))));
        }

        $source_map = ee('export:SourcesService')->getAvailable();
        $format_map = ee('export:FormatsService')->getAvailable();
        $output_map = ee('export:OutputService')->getAvailable();

        $source_choices = $this->buildChoicesFromProviderMap($source_map, 'export_source_');
        $format_choices = $this->buildChoicesFromProviderMap($format_map, 'export_format_');
        $output_choices = $this->buildChoicesFromProviderMap($output_map, 'export_output_');

        $source_group_toggle = [];
        foreach (array_keys($source_map) as $key) {
            $source_group_toggle[$key] = 'source_' . $key;
        }

        $format_group_toggle = [];
        foreach (array_keys($format_map) as $key) {
            $format_group_toggle[$key] = 'format_' . $key;
        }

        $output_group_toggle = [];
        foreach (array_keys($output_map) as $key) {
            $output_group_toggle[$key] = 'output_' . $key;
        }

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
            ->set('group_toggle', $source_group_toggle);

        $identity->getFieldSet('export_field_allowed_roles')
            ->setDesc('export_field_allowed_roles_desc')
            ->getField('allowed_roles', 'checkbox')
            ->setChoices($roles)
            ->setValue($selected_allowed_roles);

        // ── Section 2 — Source options ────────────────────────────────────────
        // One fieldset group per registered source key, built-in or third-party.

        $src = $form->getGroup('export_section_source_params');
        $this->renderPluginCpFields($src, $source_map, 'source', 'src_', $settings);

        // ── Section 3 — Column selection ─────────────────────────────────────

        $col_mode = 'all';
        $stored_fields = $settings['fields'] ?? [];
        $stored_exclude = $settings['exclude'] ?? [];
        if (!empty($stored_fields)) {
            $col_mode = 'whitelist';
        } elseif (!empty($stored_exclude)) {
            $col_mode = 'blacklist';
        }

        $fields_val = is_array($stored_fields) ? implode('|', $stored_fields) : (string)$stored_fields;
        $exclude_val = is_array($stored_exclude) ? implode('|', $stored_exclude) : (string)$stored_exclude;

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
            ->set('group_toggle', $format_group_toggle);

        $this->renderPluginCpFields($fmt, $format_map, 'format', 'fmt_', $settings);

        // ── Section 5 — Output ────────────────────────────────────────────────

        $out = $form->getGroup('export_section_output');

        $out->getFieldSet('export_section_output')
            ->setTitle('export_section_output')
            ->getField('output', 'select')
            ->setChoices($output_choices)
            ->setValue($output)
            ->set('group_toggle', $output_group_toggle);

        // output_filename is cross-cutting — every output destination needs a
        // delivered filename, so it stays hand-written rather than duplicated
        // across every Output class's getCpFields().
        $out->getFieldSet('export_field_filename')
            ->getField('output_filename', 'text')
            ->setValue($settings['output:filename'] ?? '')
            ->setRequired(true);

        $this->renderPluginCpFields($out, $output_map, 'output', 'output_', $settings);

        // ── Section 6 — Modifiers (MiniGrid) ─────────────────────────────────

        $mg = ee('CP/MiniGridInput', ['field_name' => 'modify']);
        $mg->loadAssets();
        $mg->setColumns([
            'column' => ['label' => lang('export_modifier_column')],
            'chain' => ['label' => lang('export_modifier_chain')],
        ]);
        $mg->setNoResultsText('no_rows_created', 'add_a_row');
        $mg->setBlankRow([
            ['html' => form_input('column', '', 'class="form-control" placeholder="column_name"')],
            ['html' => form_input('chain', '', 'class="form-control" placeholder="ee_date[%Y-%m-%d]|uc_first"')],
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
                    'attrs' => ['row_id' => $i + 1],
                    'columns' => [
                        ['html' => form_input('column', $mod['column'], 'class="form-control"')],
                        ['html' => form_input('chain', $mod['chain'], 'class="form-control"')],
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
