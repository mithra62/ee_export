<?php

namespace Mithra62\Export\Services;

use ExpressionEngine\Service\Validation\Result as ValidationResult;
use Mithra62\Export\Plugins\AbstractPlugin;

/**
 * CpValidationBridge — bridges driver-level EE Validation into CI form_validation.
 * Extends AbstractService to reuse the getProviderMap() / provider-scan logic.
 *
 * Each driver (source, format, output) holds its own authoritative validation rules
 * via ValidateTrait + getValidator(). Those rules use unprefixed param names
 * (e.g. 'channel', 'root_name', 'filename') while the CP form POST uses prefixed
 * names ('src_entries_channel', 'fmt_root_name', 'output_filename').
 *
 * This bridge:
 *   1. Strips POST prefixes to produce driver option arrays
 *   2. Instantiates each driver from the addon.setup.php 'export' registry
 *   3. Calls $driver->validate() (runs all EE Validation rules + custom callbacks)
 *   4. Maps ValidationResult errors back to CP field names
 *   5. Returns the mapped errors as a plain array for the caller to inject into
 *      CI's public $_field_data directly — the only reliable path given EE's
 *      legacy form_validation only supports pipe-string rules and callback_method.
 */
class CpValidationBridge extends AbstractService
{
    /**
     * Run all driver validation and return any errors as a flat array.
     *
     * The caller (AbstractRoute::validate) injects these into CI's public
     * $_field_data after run() so that form_error() / form_error_class()
     * render them inline in EE's shared form template.
     *
     * @param array $post Raw $_POST
     * @param string $source Active source key (e.g. 'entries', 'grid')
     * @return array         [cp_field_name => raw_error_message]
     */
    public function getErrors(array $post, string $source): array
    {
        $format = $post['format'] ?? 'csv';
        $output = $post['output'] ?? 'download';

        $errors = $this->runSource($post, $source);
        $errors += $this->runFormat($post, $format);
        $errors += $this->runOutput($post, $output);

        return $errors;
    }

    // ── Source ─────────────────────────────────────────────────────────────────

    protected function runSource(array $post, string $source): array
    {
        $driver = $this->instantiate('sources', $source);
        if (!$driver) {
            return [];
        }

        $prefix = 'src_' . $source . '_';
        $driver->setOptions(['source' => $source] + $this->extractPrefixed($post, $prefix));

        return $this->mapErrors($driver->validate(), $prefix);
    }

    // ── Format ─────────────────────────────────────────────────────────────────

    protected function runFormat(array $post, string $format): array
    {
        $driver = $this->instantiate('formats', $format);
        if (!$driver) {
            return [];
        }

        $driver->setOptions(['format' => $format] + $this->extractPrefixed($post, 'fmt_'));

        return $this->mapErrors($driver->validate(), 'fmt_');
    }

    // ── Output ─────────────────────────────────────────────────────────────────

    protected function runOutput(array $post, string $output): array
    {
        $driver = $this->instantiate('outputs', $output);
        if (!$driver) {
            return [];
        }

        $driver->setOptions(['output' => $output] + $this->extractPrefixed($post, 'output_'));

        return $this->mapErrors($driver->validate(), 'output_');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Strip a prefix from matching POST keys to produce unprefixed driver option names.
     *
     * Example: 'src_entries_channel' with prefix 'src_entries_' → ['channel' => $value]
     */
    protected function extractPrefixed(array $post, string $prefix): array
    {
        $params = [];
        $len = strlen($prefix);

        foreach ($post as $k => $v) {
            if (str_starts_with($k, $prefix)) {
                $params[substr($k, $len)] = $v;
            }
        }

        return $params;
    }

    /**
     * Map driver ValidationResult errors (unprefixed param names) back to CP
     * field names (prefixed) by re-adding the prefix.
     *
     * Result::getAllErrors() returns [param => [rule_name => rendered_string]].
     * The top-level 'source', 'format', 'output' keys are skipped — AbstractRoute
     * already covers those with its own 'required' CI rules.
     *
     * @return array [cp_field_name => first_error_message]
     */
    protected function mapErrors(ValidationResult $result, string $prefix): array
    {
        if ($result->isValid()) {
            return [];
        }

        $mapped = [];

        foreach ($result->getAllErrors() as $param => $rule_errors) {
            if (in_array($param, ['source', 'format', 'output'], true)) {
                continue;
            }

            $mapped[$prefix . $param] = reset($rule_errors); // first rendered error string
        }

        return $mapped;
    }

    /**
     * Instantiate a driver class from the addon.setup.php 'export' registry.
     * Uses getProviderMap() from AbstractService — same scan that SourcesService
     * and FormatsService rely on.
     *
     * Returns null silently when the registry entry or class doesn't exist.
     */
    protected function instantiate(string $layer, string $key): ?AbstractPlugin
    {
        $map = $this->getProviderMap($layer);
        $class = $map[$key] ?? null;

        if ($class && class_exists($class)) {
            $obj = new $class();
            if ($obj instanceof AbstractPlugin) {
                return $obj;
            }
        }

        return null;
    }
}
