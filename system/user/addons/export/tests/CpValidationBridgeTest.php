<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Services\CpValidationBridge;
use Mithra62\UnitTests\TestCase;

class CpValidationBridgeTest extends TestCase
{
    private CpValidationBridge $bridge;

    protected function setUp(): void
    {
        $this->bridge = new CpValidationBridge();
    }

    public function testGetErrorsEmptyWhenAllValid(): void
    {
        $post = [
            'format'           => 'csv',
            'output'           => 'download',
            'src_sql_query'    => 'SELECT 1',
            'output_download_filename'  => 'export.csv',
        ];
        $this->assertEquals([], $this->bridge->getErrors($post, 'sql'));
    }

    public function testGetErrorsReturnsMissingQueryError(): void
    {
        $post = [
            'format'          => 'csv',
            'output'          => 'download',
            'src_sql_query'   => '',
            'output_download_filename' => 'export.csv',
        ];
        $errors = $this->bridge->getErrors($post, 'sql');
        $this->assertArrayHasKey('src_sql_query', $errors);
    }

    public function testGetErrorsReturnsMissingFilenameError(): void
    {
        $post = [
            'format'          => 'csv',
            'output'          => 'download',
            'src_sql_query'   => 'SELECT 1',
            'output_download_filename' => '',
        ];
        $errors = $this->bridge->getErrors($post, 'sql');
        $this->assertArrayHasKey('output_download_filename', $errors);
    }

    public function testGetErrorsXmlMissingRootName(): void
    {
        $post = [
            'format'          => 'xml',
            'output'          => 'download',
            'src_sql_query'   => 'SELECT 1',
            'output_download_filename' => 'test.xml',
            'fmt_xml_branch_name' => 'row',
        ];
        $errors = $this->bridge->getErrors($post, 'sql');
        $this->assertArrayHasKey('fmt_xml_root_name', $errors);
    }

    public function testGetErrorsReturnsEmptyForUnknownSource(): void
    {
        $post = [
            'format'          => 'csv',
            'output'          => 'download',
            'output_download_filename' => 'test.csv',
        ];
        $this->assertEquals([], $this->bridge->getErrors($post, 'completely_unknown_source_xyz'));
    }
}
