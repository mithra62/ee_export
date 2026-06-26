<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Services\ActionsService;
use Mithra62\Export\Services\CpService;
use Mithra62\Export\Services\EntryService;
use Mithra62\Export\Services\ExcelService;
use Mithra62\Export\Services\ExportService;
use Mithra62\Export\Services\FieldsService;
use Mithra62\Export\Services\FormatsService;
use Mithra62\Export\Services\LoggerService;
use Mithra62\Export\Services\MemberService;
use Mithra62\Export\Services\ModifiersService;
use Mithra62\Export\Services\OutputService;
use Mithra62\Export\Services\ParamsService;
use Mithra62\Export\Services\SourcesService;
use Mithra62\Export\Services\XmlService;
use Mithra62\UnitTests\TestCase;

class ServiceResolutionTest extends TestCase
{
    public function testParamsServiceResolves(): void
    {
        $this->assertInstanceOf(ParamsService::class, ee('export:ParamsService'));
    }

    public function testSourcesServiceResolves(): void
    {
        $this->assertInstanceOf(SourcesService::class, ee('export:SourcesService'));
    }

    public function testFormatsServiceResolves(): void
    {
        $this->assertInstanceOf(FormatsService::class, ee('export:FormatsService'));
    }

    public function testOutputServiceResolves(): void
    {
        $this->assertInstanceOf(OutputService::class, ee('export:OutputService'));
    }

    public function testModifiersServiceResolves(): void
    {
        $this->assertInstanceOf(ModifiersService::class, ee('export:ModifiersService'));
    }

    public function testXmlServiceResolves(): void
    {
        $this->assertInstanceOf(XmlService::class, ee('export:XmlService'));
    }

    public function testExcelServiceResolves(): void
    {
        $this->assertInstanceOf(ExcelService::class, ee('export:ExcelService'));
    }

    public function testLoggerServiceResolves(): void
    {
        $this->assertInstanceOf(LoggerService::class, ee('export:LoggerService'));
    }

    public function testExportServiceResolves(): void
    {
        $this->assertInstanceOf(ExportService::class, ee('export:ExportService'));
    }

    public function testActionsServiceResolves(): void
    {
        $this->assertInstanceOf(ActionsService::class, ee('export:ActionsService'));
    }

    public function testEntryServiceResolves(): void
    {
        $this->assertInstanceOf(EntryService::class, ee('export:EntryService'));
    }

    public function testFieldsServiceResolves(): void
    {
        $this->assertInstanceOf(FieldsService::class, ee('export:FieldsService'));
    }

    public function testMemberServiceResolves(): void
    {
        $this->assertInstanceOf(MemberService::class, ee('export:MemberService'));
    }

    public function testCpServiceResolves(): void
    {
        $this->assertInstanceOf(CpService::class, ee('export:CpService'));
    }
}
