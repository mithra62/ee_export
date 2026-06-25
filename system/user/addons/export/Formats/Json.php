<?php

namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;

class Json extends AbstractFormat
{
    protected mixed $fp = null;
    protected string $stream_path = '';
    protected bool $first_row = true;

    public function getCpLabel(): ?string
    {
        return 'JSON';
    }

    public function compile(AbstractSource $source): string
    {
        $this->openFile();
        $this->writeChunk($source->getExportData());
        return $this->finalizeFile();
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function openFile(array $first_row = []): void
    {
        $this->stream_path = $this->getCacheDirPath() . $this->getCacheFilename() . '.json';
        $this->fp = fopen($this->stream_path, 'w');
        $this->first_row = true;

        if ($this->fp === false) {
            throw new \RuntimeException(
                'Export (JSON): could not open cache file for writing: ' . $this->stream_path
            );
        }

        fwrite($this->fp, '[');
    }

    public function writeChunk(array $rows): void
    {
        foreach ($rows as $row) {
            if (!$this->first_row) {
                fwrite($this->fp, ',');
            }
            fwrite($this->fp, json_encode($row));
            $this->first_row = false;
        }
    }

    public function finalizeFile(): string
    {
        if ($this->fp) {
            fwrite($this->fp, ']');
            fclose($this->fp);
            $this->fp = null;
        }

        return $this->stream_path;
    }
}
