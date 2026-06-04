<?php

namespace Mithra62\Export\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class ExcelService
{
    protected array $rows = [];
    protected array $cols = [];
    protected string $file_name = '';
    protected bool $bold_first_row = false;

    public function reset(): ExcelService
    {
        $this->rows      = [];
        $this->cols      = [];
        $this->file_name = '';
        return $this;
    }

    public function boldFirstRow(bool $bold = true): ExcelService
    {
        $this->bold_first_row = $bold;
        return $this;
    }

    public function setRows(array $rows): ExcelService
    {
        $this->rows = $rows;
        return $this;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function setCols(array $cols): ExcelService
    {
        $this->cols = $cols;
        return $this;
    }

    public function getCols(): array
    {
        return $this->cols;
    }

    public function setFilename(string $file_name): ExcelService
    {
        $this->file_name = $file_name . '.xlsx';
        return $this;
    }

    public function getFileName(): string
    {
        return $this->file_name;
    }

    public function save(string $dir): void
    {
        $path   = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $this->file_name;
        $writer = $this->buildWriter($path);
        $writer->close();
    }

    public function openWriter(string $path): Writer
    {
        return $this->buildWriter($path);
    }

    protected function buildWriter(string $path): Writer
    {
        $writer = new Writer(new Options());
        $writer->openToFile($path);

        $header_style = $this->bold_first_row ? (new Style())->setFontBold() : new Style();
        $writer->addRow(Row::fromValues($this->cols, $header_style));

        foreach ($this->rows as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }

        return $writer;
    }
}
