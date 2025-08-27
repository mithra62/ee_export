<?php
namespace Mithra62\Export\Plugins;

use Mithra62\Export\Exceptions\Sources\NoDataException;

abstract class AbstractSource extends AbstractPlugin
{

    protected array $export_data = [];

    /**
     * @return string
     * @throws NoDataException
     * @throws \Exception
     */
    abstract public function compile(): string;

    public function getExportData(): array
    {
        return $this->export_data;
    }

    public function setExportData(array $export_data): AbstractSource
    {
        $this->export_data = $export_data;
        return $this;
    }
}