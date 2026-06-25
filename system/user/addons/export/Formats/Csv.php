<?php

namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;

class Csv extends AbstractFormat
{
    protected mixed $fp = null;
    protected string $stream_path = '';
    protected bool $header_written = false;

    public function getCpLabel(): ?string
    {
        return 'CSV';
    }

    public function getCpFields(array $context = []): array
    {
        $newline_choices = [
            '\n' => 'LF (\n — Unix)',
            '\r\n' => 'CRLF (\r\n — Windows)',
            '\r' => 'CR (\r — Classic Mac)',
        ];

        return [
            [
                'name' => 'separator', 'type' => 'text', 'label' => 'export_format_separator',
                'desc' => 'export_format_separator_desc', 'default' => ',', 'maxlength' => 1,
            ],
            [
                'name' => 'enclosure', 'type' => 'text', 'label' => 'export_format_enclosure',
                'desc' => 'export_format_enclosure_desc', 'default' => '"', 'maxlength' => 1,
            ],
            [
                'name' => 'escape', 'type' => 'text', 'label' => 'export_format_escape',
                'desc' => 'export_format_escape_desc', 'default' => '\\', 'maxlength' => 1,
            ],
            [
                'name' => 'newline', 'type' => 'select', 'label' => 'export_format_newline',
                'desc' => 'export_format_newline_desc', 'choices' => $newline_choices, 'default' => '\n',
            ],
        ];
    }

    public function compile(AbstractSource $source): string
    {
        $this->openFile($source->getExportData()[0] ?? []);
        $this->writeChunk($source->getExportData());
        return $this->finalizeFile();
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function openFile(array $first_row = []): void
    {
        $this->stream_path = $this->getCacheDirPath() . $this->getCacheFilename() . '.csv';
        $this->fp = fopen($this->stream_path, 'w');
        $this->header_written = false;

        if ($this->fp === false) {
            throw new \RuntimeException(
                'Export (CSV): could not open cache file for writing: ' . $this->stream_path
            );
        }
    }

    public function writeChunk(array $rows): void
    {
        $sep = $this->getOption('separator', ',');
        $enc = $this->getOption('enclosure', '"');
        $esc = $this->getOption('escape', '\\');
        $nl = $this->getOption('newline', "\n");

        if (!$this->header_written && !empty($rows)) {
            fputcsv($this->fp, array_keys($rows[0]), $sep, $enc, $esc, $nl);
            $this->header_written = true;
        }

        foreach ($rows as $row) {
            fputcsv($this->fp, array_map([$this, 'flattenValue'], array_values($row)), $sep, $enc, $esc, $nl);
        }
    }

    /**
     * Scalars pass through unchanged; arrays are JSON-encoded so a multi-
     * dimensional field (Grid, Fluid, Relationship) still fits in one cell.
     */
    protected function flattenValue(mixed $value): string
    {
        if (is_array($value)) {
            return empty($value) ? '' : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string)($value ?? '');
    }

    public function finalizeFile(): string
    {
        if ($this->fp) {
            fclose($this->fp);
            $this->fp = null;
        }

        return $this->stream_path;
    }
}
