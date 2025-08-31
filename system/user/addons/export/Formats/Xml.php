<?php

namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;

class Xml extends AbstractFormat
{
    /**
     * @var array|string[]
     */
    protected array $rules = [
        'root_name' => 'required',
        'branch_name' => 'required',
    ];

    /**
     * @param AbstractSource $source
     * @return string
     * @throws \Exception
     */
    public function compile(AbstractSource $source): string
    {
        $content = $source->getExportData();
        $xml = ee('export:XmlService');
        $xml->setRootName($this->getOption('root_name'));
        $xml->initiate();

        foreach ($content as $i => $item) {
            $xml->startBranch($this->getOption('branch_name'));
            foreach ($item as $key => $value) {
                $xml->addXmlNodes($key, $value);
            }

            $xml->endBranch();
        }

        $export_data = $xml->getXml(false);
        $save_path = $this->getCacheDirPath() . '/' . $this->getCacheFilename() . '.xml';
        $this->writeContent($export_data, $save_path);
        return $save_path;
    }
}
