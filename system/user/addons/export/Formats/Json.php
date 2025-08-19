<?php
namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;

class Json extends AbstractFormat
{
    /**
     * @return string
     * @throws \Exception
     */
    public function compile(): string
    {
        $content = $this->getCacheContent();
        $save_path = $this->getCacheDirPath() . '/' . $this->getCacheFilename() . '.json';
        $this->writeContent(json_encode($content), $save_path);
        return $save_path;
    }
}
