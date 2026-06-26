<?php

namespace Mithra62\Export\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute as EeAbstractRoute;
use ExpressionEngine\Service\Validation\Result as ValidationResult;

/**
 * AbstractRoute — project-level base for all Export CP routes.
 *
 * Adds the shared $base_url, a permission gate, and shared form validation
 * using EE's fluent Validation service. The returned ValidationResult is
 * passed to the view as $errors so ee:_shared/form can render inline errors
 * via $errors->renderError($field) without CI's form_validation library.
 */
abstract class AbstractRoute extends EeAbstractRoute
{
    /** Base URL for all Export CP pages. */
    protected $base_url = 'addons/settings/export';

    public function __construct()
    {
        if (!ee('Permission')->hasAll('can_access_addons')) {
            show_error(lang('unauthorized_access'), 403);
        }

        parent::__construct();
    }

    /**
     * Validate Create/Edit form POST data.
     *
     * Runs bridge first so driver errors can be folded in as always-fail
     * custom rules, producing a single ValidationResult. The caller passes
     * this as $vars['errors'] to the view, which calls renderError($field).
     *
     * @param array $post Raw $_POST
     * @return ValidationResult
     */
    protected function validate(array $post): ValidationResult
    {
        $source = trim($post['source'] ?? 'entries');

        // Run bridge first so its errors can be folded in as rules below
        $bridge        = new \Mithra62\Export\Services\CpValidationBridge();
        $driver_errors = $bridge->getErrors($post, $source);

        $validator = ee('Validation')->make([
            'name'            => 'required',
            'source'          => 'required',
            'output_filename' => 'required',
        ]);

        // Numeric source params — only add rules when the field is non-empty
        $prefix = 'src_' . $source . '_';
        foreach (['limit', 'offset', 'author_id'] as $f) {
            $key = $prefix . $f;
            if (!empty($post[$key])) {
                $validator->setRule($key, 'integer');
            }
        }
        $chunk_key = $prefix . 'chunk_size';
        if (!empty($post[$chunk_key])) {
            $validator->defineRule('isNaturalNoZero', function ($k, $v) {
                return (ctype_digit((string) $v) && (int) $v > 0)
                    ?: 'This field must be a whole number greater than zero.';
            });
            $validator->setRule($chunk_key, 'isNaturalNoZero');
        }

        // Fold each bridge error in as an always-fail custom rule so it
        // appears in the same Result and renders inline via renderError()
        foreach ($driver_errors as $field => $message) {
            $msg = $message;
            $validator->defineRule('bridge_' . $field, fn($k, $v, $p, $r) => $msg);
            $validator->setRule($field, 'bridge_' . $field);
        }

        return $validator->validate($post);
    }

    /**
     * Abort with a 403 unless the current member is a Super Admin.
     * Used to gate delete and other privileged operations.
     */
    protected function requireSuperAdmin(): void
    {
        if (!ee('Permission')->isSuperAdmin()) {
            show_error(lang('unauthorized_access'), 403);
        }
    }
}
