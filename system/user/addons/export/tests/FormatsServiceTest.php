<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Exceptions\Services\FormatsServiceException;
use Mithra62\Export\Formats\Csv;
use Mithra62\Export\Formats\Json;
use Mithra62\Export\Formats\Xlsx;
use Mithra62\Export\Formats\Xml;
use Mithra62\Export\Services\FormatsService;
use Mithra62\Export\Services\ParamsService;
use Mithra62\UnitTests\TestCase;

class FormatsServiceTest extends TestCase
{
    private function service(array $params): FormatsService
    {
        $p = new ParamsService();
        $p->setParams($params);
        $s = new FormatsService();
        $s->setParams($p);
        return $s;
    }

    public function testThrowsWhenFormatParamMissing(): void
    {
        $this->expectException(FormatsServiceException::class);
        $this->service(['source' => 'sql'])->getFormat();
    }

    public function testGetFormatReturnsCsv(): void
    {
        $this->assertInstanceOf(Csv::class, $this->service(['format' => 'csv'])->getFormat());
    }

    public function testGetFormatReturnsJson(): void
    {
        $this->assertInstanceOf(Json::class, $this->service(['format' => 'json'])->getFormat());
    }

    public function testGetFormatReturnsXlsx(): void
    {
        $this->assertInstanceOf(Xlsx::class, $this->service(['format' => 'xlsx'])->getFormat());
    }

    public function testGetFormatReturnsXml(): void
    {
        $this->assertInstanceOf(Xml::class, $this->service(['format' => 'xml'])->getFormat());
    }

    public function testThrowsForUnknownFormat(): void
    {
        $this->expectException(FormatsServiceException::class);
        $this->service(['format' => 'unknown_xyz_abc'])->getFormat();
    }
}
