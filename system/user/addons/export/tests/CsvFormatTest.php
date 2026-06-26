<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Formats\Csv;
use Mithra62\UnitTests\TestCase;

class CsvFormatTest extends TestCase
{
    private Csv $csv;
    private string $path = '';

    protected function setUp(): void
    {
        $this->csv = new Csv();
        $this->csv->setCachePath(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
    }

    protected function tearDown(): void
    {
        if ($this->path && file_exists($this->path)) {
            @unlink($this->path);
        }
    }

    public function testOpenFileWriteChunkFinalizeProducesFile(): void
    {
        $this->csv->openFile();
        $this->csv->writeChunk([['col_a' => 'val1', 'col_b' => 'val2']]);
        $this->path = $this->csv->finalizeFile();

        $this->assertFileExists($this->path);
    }

    public function testFinalizeFileReturnsPathEndingInCsv(): void
    {
        $this->csv->openFile();
        $this->csv->writeChunk([['name' => 'Alice']]);
        $this->path = $this->csv->finalizeFile();

        $this->assertStringEndsWith('.csv', $this->path);
    }

    public function testHeaderRowWrittenOnce(): void
    {
        $this->csv->openFile();
        $this->csv->writeChunk([['col_a' => '1', 'col_b' => '2']]);
        $this->csv->writeChunk([['col_a' => '3', 'col_b' => '4']]);
        $this->path = $this->csv->finalizeFile();

        $content = file_get_contents($this->path);
        $this->assertEquals(1, substr_count($content, 'col_a'));
    }

    public function testColumnValuesAppearInOutput(): void
    {
        $this->csv->openFile();
        $this->csv->writeChunk([['name' => 'Alice', 'age' => 30]]);
        $this->path = $this->csv->finalizeFile();

        $content = file_get_contents($this->path);
        $this->assertStringContainsString('Alice', $content);
        $this->assertStringContainsString('30', $content);
    }

    public function testArrayValuesFlattenedToJson(): void
    {
        $this->csv->openFile();
        $this->csv->writeChunk([['tags' => ['php', 'ee']]]);
        $this->path = $this->csv->finalizeFile();

        $content = file_get_contents($this->path);
        $this->assertStringContainsString('php', $content);
    }

    public function testNullValueProducesEmptyCell(): void
    {
        $this->csv->openFile();
        $this->csv->writeChunk([['name' => null, 'age' => 25]]);
        $this->path = $this->csv->finalizeFile();

        $rows = array_map('str_getcsv', explode("\n", trim(file_get_contents($this->path))));
        // Row 1 = header, Row 2 = data; null becomes empty string
        $this->assertEquals('', $rows[1][0]);
    }

    public function testCustomSeparatorAppearsInOutput(): void
    {
        $this->csv->setOptions(['separator' => ';']);
        $this->csv->openFile();
        $this->csv->writeChunk([['a' => '1', 'b' => '2']]);
        $this->path = $this->csv->finalizeFile();

        $content = file_get_contents($this->path);
        $this->assertStringContainsString(';', $content);
    }
}
