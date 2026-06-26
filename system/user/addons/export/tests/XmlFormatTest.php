<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Formats\Xml;
use Mithra62\UnitTests\TestCase;

class XmlFormatTest extends TestCase
{
    private Xml $xml;
    private string $path = '';

    protected function setUp(): void
    {
        $this->xml = new Xml();
        $this->xml->setCachePath(sys_get_temp_dir() . DIRECTORY_SEPARATOR);
    }

    protected function tearDown(): void
    {
        if ($this->path && file_exists($this->path)) {
            @unlink($this->path);
        }
    }

    public function testValidationFailsWhenRootNameMissing(): void
    {
        $this->xml->setOptions(['format' => 'xml', 'branch_name' => 'row']);
        $this->assertFalse($this->xml->validate()->isValid());
    }

    public function testValidationFailsWhenBranchNameMissing(): void
    {
        $this->xml->setOptions(['format' => 'xml', 'root_name' => 'items']);
        $this->assertFalse($this->xml->validate()->isValid());
    }

    public function testValidationPassesWithBothNames(): void
    {
        $this->xml->setOptions(['format' => 'xml', 'root_name' => 'items', 'branch_name' => 'item']);
        $this->assertTrue($this->xml->validate()->isValid());
    }

    public function testFinalizeFileReturnsPathEndingInXml(): void
    {
        $this->xml->setOptions(['root_name' => 'items', 'branch_name' => 'item']);
        $this->xml->openFile();
        $this->path = $this->xml->finalizeFile();

        $this->assertStringEndsWith('.xml', $this->path);
    }

    public function testWriteChunkProducesValidXml(): void
    {
        $this->xml->setOptions(['root_name' => 'items', 'branch_name' => 'item']);
        $this->xml->openFile();
        $this->xml->writeChunk([['name' => 'Alice', 'age' => '30']]);
        $this->path = $this->xml->finalizeFile();

        $doc = simplexml_load_file($this->path);
        $this->assertNotFalse($doc);
    }

    public function testFieldValuesAppearInXmlOutput(): void
    {
        $this->xml->setOptions(['root_name' => 'items', 'branch_name' => 'item']);
        $this->xml->openFile();
        $this->xml->writeChunk([['name' => 'Alice']]);
        $this->path = $this->xml->finalizeFile();

        $this->assertStringContainsString('Alice', file_get_contents($this->path));
    }
}
