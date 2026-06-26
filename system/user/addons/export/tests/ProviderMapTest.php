<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Fields\Date as FieldDate;
use Mithra62\Export\Formats\Csv;
use Mithra62\Export\Formats\Json;
use Mithra62\Export\Formats\Xlsx;
use Mithra62\Export\Formats\Xml;
use Mithra62\Export\Modifiers\UcFirst;
use Mithra62\Export\Output\Download;
use Mithra62\Export\Output\Local;
use Mithra62\Export\Services\SourcesService;
use Mithra62\Export\Sources\Entries;
use Mithra62\Export\Sources\Members;
use Mithra62\Export\Sources\Sql;
use Mithra62\UnitTests\TestCase;

class ProviderMapTest extends TestCase
{
    private SourcesService $service;

    protected function setUp(): void
    {
        $this->service = new SourcesService();
    }

    public function testSourcesMapContainsEntries(): void
    {
        $map = $this->service->getProviderMap('sources');
        $this->assertArrayHasKey('entries', $map);
        $this->assertEquals(Entries::class, $map['entries']);
    }

    public function testSourcesMapContainsMembers(): void
    {
        $map = $this->service->getProviderMap('sources');
        $this->assertArrayHasKey('members', $map);
        $this->assertEquals(Members::class, $map['members']);
    }

    public function testSourcesMapContainsSql(): void
    {
        $map = $this->service->getProviderMap('sources');
        $this->assertArrayHasKey('sql', $map);
        $this->assertEquals(Sql::class, $map['sql']);
    }

    public function testFormatsMapContainsCsv(): void
    {
        $map = $this->service->getProviderMap('formats');
        $this->assertArrayHasKey('csv', $map);
        $this->assertEquals(Csv::class, $map['csv']);
    }

    public function testFormatsMapContainsJson(): void
    {
        $map = $this->service->getProviderMap('formats');
        $this->assertArrayHasKey('json', $map);
        $this->assertEquals(Json::class, $map['json']);
    }

    public function testFormatsMapContainsXlsx(): void
    {
        $map = $this->service->getProviderMap('formats');
        $this->assertArrayHasKey('xlsx', $map);
        $this->assertEquals(Xlsx::class, $map['xlsx']);
    }

    public function testFormatsMapContainsXml(): void
    {
        $map = $this->service->getProviderMap('formats');
        $this->assertArrayHasKey('xml', $map);
        $this->assertEquals(Xml::class, $map['xml']);
    }

    public function testOutputsMapContainsDownload(): void
    {
        $map = $this->service->getProviderMap('outputs');
        $this->assertArrayHasKey('download', $map);
        $this->assertEquals(Download::class, $map['download']);
    }

    public function testOutputsMapContainsLocal(): void
    {
        $map = $this->service->getProviderMap('outputs');
        $this->assertArrayHasKey('local', $map);
        $this->assertEquals(Local::class, $map['local']);
    }

    public function testModifiersMapContainsUcFirst(): void
    {
        $map = $this->service->getProviderMap('modifiers');
        $this->assertArrayHasKey('uc_first', $map);
        $this->assertEquals(UcFirst::class, $map['uc_first']);
    }

    public function testFieldsMapContainsDate(): void
    {
        $map = $this->service->getProviderMap('fields');
        $this->assertArrayHasKey('date', $map);
        $this->assertEquals(FieldDate::class, $map['date']);
    }
}
