<?php

namespace Mithra62\Export\Plugins;

use Mithra62\Export\Exceptions\Sources\NoDataException;

abstract class AbstractSource extends AbstractPlugin
{
    /**
     * @var array
     */
    protected array $export_data = [];

    /**
     * @return string
     * @throws NoDataException
     * @throws \Exception
     */
    abstract public function compile(): string;

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
     * @param array $data
     * @return array
     */
    public function cleanFields(array $data): array
    {
        if ($this->getOption('fields')) {
            foreach ($data as $key => $value) {
                if (!in_array($key, $this->getOption('fields'))) {
                    unset($data[$key]);
                }
            }

            //now we order 'em
            $return = [];
            foreach ($this->getOption('fields') as $field) {
                if(isset($data[$field])) {
                    $return[$field] = $data[$field];
                }
            }

            $data = $return;
        }

        return $data;
    }
}