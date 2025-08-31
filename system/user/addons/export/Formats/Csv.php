<?php
namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;

class Csv extends AbstractFormat
{
    public function compile(AbstractSource $source): string
    {
        $save_path = $this->getCacheDirPath() . '/' . $this->getCacheFilename() . '.csv';
        $content = $source->getExportData();
        $separator = $this->getOption('separator', ',');
        $enclosure = $this->getOption('enclosure', '"');
        $escape = $this->getOption('escape', '\\');
        $newline = $this->getOption('newline', "\n");

        $fp = fopen($save_path, 'w');
        $cols = array_keys($content[0]);
        fputcsv($fp, $cols, $separator, $enclosure, $escape, $newline);
        foreach ($content as $fields) {
            fputcsv($fp, $fields, $separator, $enclosure, $escape, $newline);
        }

        return $save_path;
    }
}
