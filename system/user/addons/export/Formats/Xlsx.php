<?php
namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class Xlsx extends AbstractFormat
{
    protected string $stream_path = '';
    protected ?Writer $writer = null;

    public function compile(AbstractSource $source): string
    {
        $rows = $source->getExportData();
        $this->openFile($rows[0] ?? []);
        $this->writeChunk($rows);
        return $this->finalizeFile();
    }

    public function supportsStreaming(): bool { return true; }

    public function openFile(array $first_row = []): void
    {
        $this->stream_path = $this->getCacheDirPath() . $this->getCacheFilename() . '.xlsx';
        $this->writer      = new Writer(new Options());
        $this->writer->openToFile($this->stream_path);

        if (!empty($first_row)) {
            $style = $this->getOption('bold_cols') === true
                ? (new Style())->setFontBold()
                : new Style();

            $this->writer->addRow(Row::fromValues(array_keys($first_row), $style));
        }
    }

    public function writeChunk(array $rows): void
    {
        foreach ($rows as $row) {
            $this->writer->addRow(Row::fromValues(array_values($row)));
        }
    }

    public function finalizeFile(): string
    {
        if ($this->writer) {
            $this->writer->close();
            $this->writer = null;
        }

        return $this->stream_path;
    }
}
