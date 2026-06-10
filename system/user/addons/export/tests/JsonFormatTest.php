<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Formats\Json;
use Mithra62\UnitTests\TestCase;

class JsonFormatTest extends TestCase
{
    private Json $json;
    private string $path = '';

    protected function setUp(): void
    {
        $this->json = new Json();
        $this->json->setCachePath(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
    }

    protected function tearDown(): void
    {
        if ($this->path && file_exists($this->path)) {
            @unlink($this->path);
        }
    }

    public function testFinalizeFileReturnsPathEndingInJson(): void
    {
        $this->json->openFile();
        $this->path = $this->json->finalizeFile();

        $this->assertStringEndsWith('.json', $this->path);
    }

    public function testOutputIsValidJsonArray(): void
    {
        $this->json->openFile();
        $this->json->writeChunk([['name' => 'Alice', 'age' => 30]]);
        $this->path = $this->json->finalizeFile();

        $decoded = json_decode(file_get_contents($this->path), true);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertIsArray($decoded);
    }

    public function testSingleRowWritten(): void
    {
        $this->json->openFile();
        $this->json->writeChunk([['name' => 'Alice']]);
        $this->path = $this->json->finalizeFile();

        $decoded = json_decode(file_get_contents($this->path), true);
        $this->assertCount(1, $decoded);
        $this->assertEquals('Alice', $decoded[0]['name']);
    }

    public function testMultipleChunksAccumulate(): void
    {
        $this->json->openFile();
        $this->json->writeChunk([['id' => 1]]);
        $this->json->writeChunk([['id' => 2]]);
        $this->path = $this->json->finalizeFile();

        $decoded = json_decode(file_get_contents($this->path), true);
        $this->assertCount(2, $decoded);
    }

    public function testEmptyChunkProducesEmptyArray(): void
    {
        $this->json->openFile();
        $this->json->writeChunk([]);
        $this->path = $this->json->finalizeFile();

        $content = file_get_contents($this->path);
        $this->assertStringStartsWith('[', $content);
        $this->assertStringEndsWith(']', $content);
        $decoded = json_decode($content, true);
        $this->assertCount(0, $decoded);
    }

    public function testOutputWrappedInJsonArray(): void
    {
        $this->json->openFile();
        $this->json->writeChunk([['x' => 1]]);
        $this->path = $this->json->finalizeFile();

        $content = file_get_contents($this->path);
        $this->assertStringStartsWith('[', $content);
        $this->assertStringEndsWith(']', $content);
    }
}
