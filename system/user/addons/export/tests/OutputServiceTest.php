<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Exceptions\Services\OutputServiceException;
use Mithra62\Export\Output\Download;
use Mithra62\Export\Output\Local;
use Mithra62\Export\Services\OutputService;
use Mithra62\Export\Services\ParamsService;
use Mithra62\UnitTests\TestCase;

class OutputServiceTest extends TestCase
{
    private function service(array $params): OutputService
    {
        $p = new ParamsService();
        $p->setParams($params);
        $s = new OutputService();
        $s->setParams($p);
        return $s;
    }

    public function testThrowsWhenOutputParamMissing(): void
    {
        $this->expectException(OutputServiceException::class);
        $this->service(['format' => 'csv'])->getDestination();
    }

    public function testGetDestinationReturnsDownload(): void
    {
        $dest = $this->service(['output' => 'download', 'output:filename' => 'test.csv'])->getDestination();
        $this->assertInstanceOf(Download::class, $dest);
    }

    public function testGetDestinationReturnsLocal(): void
    {
        $dest = $this->service([
            'output' => 'local',
            'output:filename' => 'test.csv',
            'output:path' => sys_get_temp_dir(),
        ])->getDestination();
        $this->assertInstanceOf(Local::class, $dest);
    }

    public function testThrowsForUnknownOutput(): void
    {
        $this->expectException(OutputServiceException::class);
        $this->service(['output' => 'unknown_xyz_abc'])->getDestination();
    }
}
