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
     * Validate Create/Edit form POST data using CI's form_validation library.
     *
     * Errors are stored in CI's form_validation state, which EE's
     * _shared/form/fieldset.php reads via form_error_class() to add the
     * `fieldset-invalid` class, and _shared/form/field.php reads via
     * form_error() to render the inline error message — both without any
     * custom $errors object being passed to the view.
     *
     * @param  array $post  Raw $_POST
     * @return bool         True when all rules pass; false when any fail
     */
    protected function validate(array $post): bool
    {
        ee()->load->library('form_validation');

        // Match EE's standard inline error markup used across all CP forms.
        ee()->form_validation->set_error_delimiters(
            '<em class="ee-form-error-message">',
            '</em>'
        );

        ee()->form_validation->set_rules('name',            lang('export_field_name'),     'required');
        ee()->form_validation->set_rules('source',          lang('export_field_source'),   'required');
        ee()->form_validation->set_rules('output_filename', lang('export_field_filename'), 'required');

        // XML format requires root and branch element names.
        // We check $_POST['format'] before running so we only add the
        // required rules when they are actually relevant.
        if (($post['format'] ?? '') === 'xml') {
            ee()->form_validation->set_rules('fmt_root_name',   lang('export_format_root_name'),   'required');
            ee()->form_validation->set_rules('fmt_branch_name', lang('export_format_branch_name'), 'required');
        }

        return ee()->form_validation->run();
    }
}
