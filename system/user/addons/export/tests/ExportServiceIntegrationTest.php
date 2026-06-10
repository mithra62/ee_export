<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Services\ExportService;
use Mithra62\UnitTests\TestCase;

class ExportServiceIntegrationTest extends TestCase
{
    private function service(): ExportService
    {
        return ee('export:ExportService');
    }

    // ── validate() ────────────────────────────────────────────────────────────

    public function testValidateReturnsTrueWithValidSqlDownloadParams(): void
    {
        $service = $this->service();
        $service->setParameters([
            'source'           => 'sql',
            'source:query'     => 'SELECT 1',
            'format'           => 'csv',
            'output'           => 'download',
            'output:filename'  => 'test.csv',
        ]);
        $this->assertTrue($service->validate());
    }

    public function testValidateReturnsFalseForInvalidSql(): void
    {
        $service = $this->service();
        $service->setParameters([
            'source'           => 'sql',
            'source:query'     => 'UPDATE exp_members SET screen_name = "x"',
            'format'           => 'csv',
            'output'           => 'download',
            'output:filename'  => 'test.csv',
        ]);
        $this->assertFalse($service->validate());
    }

    public function testValidateReturnsFalseWhenXmlMissingRootName(): void
    {
        $service = $this->service();
        $service->setParameters([
            'source'          => 'sql',
            'source:query'    => 'SELECT 1',
            'format'          => 'xml',
            'format:branch_name' => 'row',
            'output'          => 'download',
            'output:filename' => 'test.xml',
        ]);
        $this->assertFalse($service->validate());
    }

    public function testGetErrorsPopulatedAfterFailedValidation(): void
    {
        $service = $this->service();
        $service->setParameters([
            'source'           => 'sql',
            'source:query'     => 'DROP TABLE exp_members',
            'format'           => 'csv',
            'output'           => 'download',
            'output:filename'  => 'test.csv',
        ]);
        $service->validate();
        $this->assertNotEmpty($service->getErrors());
    }

    public function testGetErrorsEmptyAfterSuccessfulValidation(): void
    {
        $service = $this->service();
        $service->setParameters([
            'source'           => 'sql',
            'source:query'     => 'SELECT 1',
            'format'           => 'csv',
            'output'           => 'download',
            'output:filename'  => 'test.csv',
        ]);
        $service->validate();
        $this->assertEmpty($service->getErrors());
    }

    // ── build() ───────────────────────────────────────────────────────────────

    public function testBuildProducesLocalOutputFile(): void
    {
        $tmpDir  = sys_get_temp_dir();
        $outFile = $tmpDir . DIRECTORY_SEPARATOR . 'export_integration_test_' . uniqid() . '.csv';
        $outName = basename($outFile);

        $service = $this->service();
        $service->setParameters([
            'source'           => 'sql',
            'source:query'     => 'SELECT 1 AS test_col',
            'format'           => 'csv',
            'output'           => 'local',
            'output:path'      => $tmpDir,
            'output:filename'  => $outName,
        ]);
        $service->build();

        $this->assertFileExists($outFile);
        @unlink($outFile);
    }

    public function testBuildOutputFileContainsData(): void
    {
        $tmpDir  = sys_get_temp_dir();
        $outName = 'export_content_test_' . uniqid() . '.csv';
        $outFile = $tmpDir . DIRECTORY_SEPARATOR . $outName;

        $service = $this->service();
        $service->setParameters([
            'source'           => 'sql',
            'source:query'     => 'SELECT 1 AS test_col',
            'format'           => 'csv',
            'output'           => 'local',
            'output:path'      => $tmpDir,
            'output:filename'  => $outName,
        ]);
        $service->build();

        $content = file_get_contents($outFile);
        $this->assertStringContainsString('test_col', $content);
        @unlink($outFile);
    }
}
