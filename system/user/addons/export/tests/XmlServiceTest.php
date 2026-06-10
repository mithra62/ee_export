<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Services\XmlService;
use Mithra62\UnitTests\TestCase;

class XmlServiceTest extends TestCase
{
    public function testInstantiates(): void
    {
        $this->assertInstanceOf(XmlService::class, new XmlService());
    }

    public function testSetRootNameReturnsFluent(): void
    {
        $x = new XmlService();
        $this->assertInstanceOf(XmlService::class, $x->setRootName('root'));
    }

    public function testInitiateProducesXmlDeclaration(): void
    {
        $x = new XmlService();
        $x->setRootName('items');
        $x->initiate();
        $xml = $x->getXml();
        $this->assertStringContainsString('<?xml', $xml);
    }

    public function testInitiateProducesRootElement(): void
    {
        $x = new XmlService();
        $x->setRootName('items');
        $x->initiate();
        $xml = $x->getXml();
        $this->assertStringContainsString('<items', $xml);
    }

    public function testStartAndEndBranchCreatesChildElement(): void
    {
        $x = new XmlService();
        $x->setRootName('root');
        $x->initiate();
        $x->startBranch('item');
        $x->endBranch();
        $xml = $x->getXml();
        $this->assertStringContainsString('<item', $xml);
    }

    public function testAddNodeWritesScalarValue(): void
    {
        $x = new XmlService();
        $x->setRootName('root');
        $x->initiate();
        $x->startBranch('row');
        $x->addNode('name', 'Alice');
        $x->endBranch();
        $xml = $x->getXml();
        $this->assertStringContainsString('<name>', $xml);
        $this->assertStringContainsString('Alice', $xml);
    }

    public function testAddXmlNodesScalarValue(): void
    {
        $x = new XmlService();
        $x->setRootName('root');
        $x->initiate();
        $x->startBranch('row');
        $x->addXmlNodes('title', 'Hello Export');
        $x->endBranch();
        $xml = $x->getXml();
        $this->assertStringContainsString('Hello Export', $xml);
    }

    public function testAddXmlNodesNumericKeyUsesItemWithIndex(): void
    {
        $x = new XmlService();
        $x->setRootName('root');
        $x->initiate();
        $x->startBranch('row');
        $x->addXmlNodes(0, 'first');
        $x->endBranch();
        $xml = $x->getXml();
        $this->assertStringContainsString('index="0"', $xml);
    }

    public function testAddXmlNodesNestedArrayCreatesChildElements(): void
    {
        $x = new XmlService();
        $x->setRootName('root');
        $x->initiate();
        $x->startBranch('row');
        $x->addXmlNodes('related', ['entry_id' => 5, 'title' => 'Some Entry']);
        $x->endBranch();
        $xml = $x->getXml();
        $this->assertStringContainsString('<related', $xml);
        $this->assertStringContainsString('entry_id', $xml);
    }

    public function testSetCharSetAppearsInXmlDeclaration(): void
    {
        $x = new XmlService();
        $x->setRootName('root');
        $x->setCharSet('UTF-8');
        $x->initiate();
        $xml = $x->getXml();
        $this->assertStringContainsString('UTF-8', $xml);
    }

    public function testInitiateFileWritesToDisk(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'export_xml_test_' . uniqid() . '.xml';
        $x = new XmlService();
        $x->setRootName('rows');
        $x->initiateFile($path);
        $x->startBranch('row');
        $x->addNode('name', 'test');
        $x->endBranch();
        $x->closeFile();

        $this->assertFileExists($path);
        $this->assertStringContainsString('test', file_get_contents($path));

        unlink($path);
    }
}
