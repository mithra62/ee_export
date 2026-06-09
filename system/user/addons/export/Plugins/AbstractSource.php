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

    public function supportsStreaming(): bool { return false; }

    public function openStream(): void {}

    public function nextChunk(): array { return []; }

    public function closeStream(): void {}

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
        $whitelist = $this->getOption('fields', []);
        if (!empty($whitelist)) {
            $filtered = [];
            foreach ($whitelist as $key) {
                if (array_key_exists($key, $data)) {
                    $filtered[$key] = $data[$key];
                }
            }
            return $filtered;
        }

        $exclude = $this->getOption('exclude', []);
        if (!empty($exclude)) {
            foreach ($exclude as $key) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}