<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Exceptions\Services\SourcesServiceException;
use Mithra62\Export\Services\ParamsService;
use Mithra62\Export\Services\SourcesService;
use Mithra62\Export\Sources\Entries;
use Mithra62\Export\Sources\Members;
use Mithra62\Export\Sources\Sql;
use Mithra62\UnitTests\TestCase;

class SourcesServiceTest extends TestCase
{
    private function service(array $params): SourcesService
    {
        $p = new ParamsService();
        $p->setParams($params);
        $s = new SourcesService();
        $s->setParams($p);
        return $s;
    }

    public function testThrowsWhenSourceParamMissing(): void
    {
        $this->expectException(SourcesServiceException::class);
        $this->service(['format' => 'csv'])->getSource();
    }

    public function testGetSourceReturnsSqlSource(): void
    {
        $source = $this->service(['source' => 'sql', 'source:query' => 'SELECT 1'])->getSource();
        $this->assertInstanceOf(Sql::class, $source);
    }

    public function testGetSourceReturnsEntriesSource(): void
    {
        $source = $this->service(['source' => 'entries', 'source:channel' => 'default'])->getSource();
        $this->assertInstanceOf(Entries::class, $source);
    }

    public function testGetSourceReturnsMembersSource(): void
    {
        $source = $this->service(['source' => 'members'])->getSource();
        $this->assertInstanceOf(Members::class, $source);
    }

    public function testThrowsForUnknownSource(): void
    {
        $this->expectException(SourcesServiceException::class);
        $this->service(['source' => 'nonexistent_source_xyz_abc'])->getSource();
    }

    public function testGetSourceSetsOptionsOnReturnedObject(): void
    {
        $source = $this->service(['source' => 'sql', 'source:query' => 'SELECT 1'])->getSource();
        $this->assertEquals('SELECT 1', $source->getOption('query'));
    }
}
