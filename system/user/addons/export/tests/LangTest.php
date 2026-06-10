<?php

namespace Mithra62\Export\Tests;

use Mithra62\UnitTests\TestCase;

class LangTest extends TestCase
{
    public function testLangFileExists(): void
    {
        $this->assertNotNull(realpath(PATH_THIRD . 'export/language/english/export_lang.php'));
    }

    public function testLangFormat(): array
    {
        include PATH_THIRD . 'export/language/english/export_lang.php';
        $this->assertTrue(isset($lang));
        $this->assertIsArray($lang);
        return $lang;
    }

    /**
     * @depends testLangFormat
     */
    public function testModuleNameKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_module_name', $lang);
    }

    /**
     * @depends testLangFormat
     */
    public function testModuleDescriptionKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_module_description', $lang);
    }

    /**
     * @depends testLangFormat
     */
    public function testCpHeadingKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_cp_heading', $lang);
    }

    /**
     * @depends testLangFormat
     */
    public function testSourceEntriesKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_source_entries', $lang);
    }

    /**
     * @depends testLangFormat
     */
    public function testSourceMembersKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_source_members', $lang);
    }

    /**
     * @depends testLangFormat
     */
    public function testSourceSqlKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_source_sql', $lang);
    }

    /**
     * @depends testLangFormat
     */
    public function testOutputDownloadKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_output_download', $lang);
    }

    /**
     * @depends testLangFormat
     */
    public function testOutputLocalKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_output_local', $lang);
    }

    /**
     * @depends testLangFormat
     */
    public function testErrHeadingKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_err_heading', $lang);
    }

    /**
     * @depends testLangFormat
     */
    public function testSettingsKeyExists(array $lang): void
    {
        $this->assertArrayHasKey('export_settings', $lang);
    }
}
