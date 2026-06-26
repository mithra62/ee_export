<?php

namespace Mithra62\Export\Plugins;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Services\ModifiersService;

abstract class AbstractSource extends AbstractPlugin
{
    /**
     * @var array
     */
    protected array $export_data = [];

    /**
     * @var ModifiersService
     */
    protected ModifiersService $post_process;

    /**
     * @return string
     * @throws NoDataException
     * @throws \Exception
     */
    abstract public function compile(): AbstractSource;

    public function supportsStreaming(): bool
    {
        return false;
    }

    public function openStream(): void
    {
    }

    public function nextChunk(): array
    {
        return [];
    }

    public function closeStream(): void
    {
    }

    /**
     * @param ModifiersService $post_process
     * @return $this
     */
    public function setPostProcess(ModifiersService $post_process): AbstractSource
    {
        $this->post_process = $post_process;
        return $this;
    }

    /**
     * @return ModifiersService
     */
    public function getPostProcess(): ModifiersService
    {
        return $this->post_process;
    }

    /**
     * @return array
     */
    public function getExportData(): array
    {
        return $this->export_data;
    }

    /**
     * @param array $export_data
     * @return $this
     */
    public function setExportData(array $export_data): AbstractSource
    {
        $this->export_data = $export_data;
        return $this;
    }

    /**
     * Filter output columns using the `fields` whitelist or `exclude` blacklist tag params.
     *
     * Priority rules:
     *   1. `fields` present  → return only those columns, in declaration order (ignore `exclude`)
     *   2. `exclude` present → remove listed columns, return the rest
     *   3. Neither present   → return the full row unchanged
     *
     * The `fields` param also lets template authors reorder columns — the
     * returned array preserves the order of the `fields` list, not the source.
     *
     * @param array $data
     * @return array
     */
    public function cleanFields(array $data): array
    {
        $whitelist = $this->normalizeList($this->getOption('fields', []));
        if (!empty($whitelist)) {
            $filtered = [];
            foreach ($whitelist as $key) {
                if (array_key_exists($key, $data)) {
                    $filtered[$key] = $data[$key];
                }
            }
            return $filtered;
        }

        $exclude = $this->normalizeList($this->getOption('exclude', []));
        if (!empty($exclude)) {
            foreach ($exclude as $key) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Shared status choices for any source that filters on entry status
     * (Entries, Grid, Fluid). Centralised so getCpFields() overrides don't
     * each repeat the same literal array.
     *
     * @return array<string, string>
     */
    protected static function statusChoices(): array
    {
        return [
            'open'   => lang('export_status_open'),
            'closed' => lang('export_status_closed'),
            'all'    => lang('export_status_all'),
        ];
    }

    /**
     * Shared CP field descriptor for a date-range bound, rendered as a native
     * HTML5 date picker. Used by any source filtering on a Unix-timestamp
     * column (Members' join/last_login bounds, Entries' entry_date bounds).
     *
     * The stored value is normalised to Y-m-d for the input's value attribute
     * regardless of whether it arrived as a Unix timestamp or a date string —
     * both shapes occur depending on whether the value came from a freshly
     * submitted form or from previously stored settings.
     *
     * @param string $name  Bare param name (e.g. 'entry_date_start')
     * @param string $label Lang key for the field's displayed title
     * @return array
     */
    protected static function dateFieldDescriptor(string $name, string $label): array
    {
        return [
            'name' => $name, 'type' => 'html', 'label' => $label,
            'content_callback' => function ($c) use ($name) {
                $raw = $c['settings'][$name] ?? '';
                $value = '';
                if ($raw !== '') {
                    $ts = is_numeric($raw) ? (int) $raw : @strtotime($raw);
                    $value = ($ts && $ts !== -1) ? date('Y-m-d', $ts) : $raw;
                }
                return '<input type="date" name="' . $c['field_name']
                    . '" value="' . htmlspecialchars($value) . '" class="form-control">';
            },
        ];
    }

    /**
     * Normalise a fields/exclude option value to a plain array of trimmed strings.
     *
     * Options can arrive in two shapes depending on call-site:
     *   - Template tag params  → pipe-separated string  ("password|salt")
     *   - Stored CP settings   → JSON-decoded array      (["password", "salt"])
     *
     * Both forms are reduced to the same array so cleanFields() can iterate
     * safely without caring where the value originated.
     */
    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('trim', explode('|', $value))));
        }

        return [];
    }
}