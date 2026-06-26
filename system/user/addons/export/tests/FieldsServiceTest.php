<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Fields\Date;
use Mithra62\Export\Services\FieldsService;
use Mithra62\UnitTests\TestCase;

class FieldsServiceTest extends TestCase
{
    private FieldsService $service;

    protected function setUp(): void
    {
        $this->service = ee('export:FieldsService');
    }

    public function testGetAllReturnsArray(): void
    {
        $this->assertIsArray($this->service->getAll());
    }

    public function testGetAllContainsDateKey(): void
    {
        $this->assertArrayHasKey('date', $this->service->getAll());
    }

    public function testGetFieldReturnsDateHandler(): void
    {
        $this->assertInstanceOf(Date::class, $this->service->getField('date'));
    }

    public function testGetFieldReturnsNullForUnknownType(): void
    {
        $this->assertNull($this->service->getField('no_such_field_type_xyz_abc'));
    }

    public function testGetFieldIsCachedBetweenCalls(): void
    {
        $first  = $this->service->getField('date');
        $second = $this->service->getField('date');
        $this->assertSame($first, $second);
    }
}
