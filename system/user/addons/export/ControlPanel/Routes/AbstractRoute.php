<?php

namespace Mithra62\Export\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute as EeAbstractRoute;

/**
 * AbstractRoute — project-level base for all Export CP routes.
 *
 * Adds the shared $base_url, a permission gate, and shared form validation
 * that populates CI's form_validation library so EE's built-in
 * form_error_class() / form_error() mechanisms handle field highlighting
 * and inline error messages without any secondary $errors layer.
 */
abstract class AbstractRoute extends EeAbstractRoute
{
    /** Base URL for all Export CP pages. */
    protected $base_url = 'addons/settings/export';

    public function __construct()
    {
        if (! ee('Permission')->hasAll('can_access_addons')) {
            show_error(lang('unauthorized_access'), 403);
        }

        parent::__construct();
    }

    /**
     * Validate Create/Edit form POST data.
     *
     * Two-step process:
     *   1. Register core CP-layer CI rules (name, source, output_filename, numeric
     *      fields). These are simple constraints that don't require driver context.
     *   2. Run CpValidationBridge, which instantiates the active source / format /
     *      output driver, calls their built-in EE Validation rules (including custom
     *      rules like validChannel, isSelect, dirExists), maps any errors back to CP
     *      field names, and injects them into CI form_validation as always-fail
     *      callable rules — all before run() fires.
     *
     * After both steps, a single ee()->form_validation->run() covers everything.
     * Errors end up in CI's state, so EE's fieldset.php reads them via
     * form_error($field) / form_error_class($field) and renders them inline.
     *
     * @param  array $post  Raw $_POST
     * @return bool         True when all rules pass; false when any fail
     */
    protected function validate(array $post): bool
    {
        $source = trim($post['source'] ?? 'entries');

        ee()->load->library('form_validation');
        ee()->form_validation->set_error_delimiters(
            '<em class="ee-form-error-message">',
            '</em>'
        );

        // ── Step 1: Core CP-layer rules (always apply) ────────────────────────
        ee()->form_validation->set_rules('name',            lang('export_field_name'),     'required');
        ee()->form_validation->set_rules('source',          lang('export_field_source'),   'required');
        ee()->form_validation->set_rules('output_filename', lang('export_field_filename'), 'required');

        // Numeric source params — only validate when the field is non-empty so
        // optional fields don't fail when left blank.
        $prefix = 'src_' . $source . '_';
        foreach (['limit', 'offset', 'author_id'] as $f) {
            $key = $prefix . $f;
            if (isset($post[$key]) && $post[$key] !== '') {
                ee()->form_validation->set_rules($key, $f, 'is_natural');
            }
        }
        $chunk_key = $prefix . 'chunk_size';
        if (! empty($post[$chunk_key])) {
            ee()->form_validation->set_rules($chunk_key, 'chunk_size', 'is_natural_no_zero');
        }

        // ── Step 2: CI run() for the core rules registered above ─────────────
        $ci_valid = ee()->form_validation->run();

        // ── Step 3: Driver rules via bridge ───────────────────────────────────
        // EE's legacy form_validation only supports pipe-string rules and the
        // callback_method format — PHP callable arrays are silently ignored.
        // The bridge therefore returns errors as a plain array, which we inject
        // directly into CI's public $_field_data so that form_error() and
        // form_error_class() render them inline without any extra view variable.
        //
        // Note: error() applies $_error_prefix/_error_suffix when reading, so we
        // store the raw message (no HTML wrapping needed here).
        $bridge        = new \Mithra62\Export\Services\CpValidationBridge();
        $driver_errors = $bridge->getErrors($post, $source);

        foreach ($driver_errors as $cp_field => $message) {
            ee()->form_validation->_field_data[$cp_field] = [
                'field'    => $cp_field,
                'label'    => $cp_field,
                'rules'    => '',
                'is_array' => false,
                'postdata' => $post[$cp_field] ?? '',
                'error'    => $message,
            ];
        }

        return $ci_valid && empty($driver_errors);
    }

    /**
     * Abort with a 403 unless the current member is a Super Admin.
     * Used to gate delete and other privileged operations.
     */
    protected function requireSuperAdmin(): void
    {
        if (! ee('Permission')->isSuperAdmin()) {
            show_error(lang('unauthorized_access'), 403);
        }
    }
}
