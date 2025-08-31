<?php
namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;

class Xlsx extends AbstractFormat
{
    /**
     * @param AbstractSource $source
     * @return string
     */
    public function compile(AbstractSource $source): string
    {
        $content = $source->getExportData();
        $cols = array_keys($content[0]);
        $xlsx = ee('export:ExcelService')->setRows($content)
            ->setCols($cols)
            ->setFilename($this->getCacheFilename());

        if($this->getOption('bold_cols') === true) {
            $xlsx->boldFirstRow(true);
        }

        $xlsx->save($this->getCacheDirPath());

        return $this->getCacheDirPath() . '/' . $this->getCacheFilename() . '.xlsx';
    }
}
