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
     * Remove any columns listed in the `exclude` tag param from the row.
     * When `exclude` is not set the full row is returned unchanged, so new
     * fields added to a channel are automatically included without any tag
     * edits.
     *
     * @param array $data
     * @return array
     */
    public function cleanFields(array $data): array
    {
        $exclude = $this->getOption('exclude', []);
        if (!empty($exclude)) {
            foreach ($exclude as $field) {
                unset($data[$field]);
            }
        }

        return $data;
    }
}