<?php
namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;

class Xlsx extends AbstractFormat
{
    public function compile(): string
    {
        $content = $this->getCacheContent();
        $cols = array_keys($content[0]);
        $xlsx = ee('export:ExcelService')->setRows($content)
            ->setCols($cols)
            ->setFilename($this->getCacheFilename());

        if($this->getOption('bold_cols') == 'y') {
            $xlsx->boldFirstRow(true);
        }

        $xlsx->save($this->getCacheDirPath());

        return $this->getCacheDirPath() . '/' . $this->getCacheFilename() . '.xlsx';
    }
}
