<?php

namespace Mithra62\Export\Tests;

use ExpressionEngine\Core\Provider;
use Mithra62\UnitTests\TestCase;

class AddonSetupTest extends TestCase
{
    public function testFileExists(): void
    {
        $this->assertNotNull(realpath(PATH_THIRD . 'export/addon.setup.php'));
    }

    public function testAuthorValue(): Provider
    {
        $addon = ee('App')->get('export');
        $this->assertEquals('mithra62', $addon->getAuthor());
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testNameValue(Provider $addon): Provider
    {
        $this->assertEquals('Export', $addon->getName());
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testNamespaceValue(Provider $addon): Provider
    {
        $this->assertEquals('Mithra62\Export', $addon->getNamespace());
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testVersionValue(Provider $addon): Provider
    {
        $this->assertNotEmpty($addon->get('version'));
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testSettingsExistValue(Provider $addon): Provider
    {
        $this->assertTrue($addon->get('settings_exist'));
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testTestsPathDeclared(Provider $addon): Provider
    {
        $tests = $addon->get('tests');
        $this->assertIsArray($tests);
        $this->assertArrayHasKey('path', $tests);
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testExportLayerSourcesRegistered(Provider $addon): Provider
    {
        $export = $addon->get('export');
        $this->assertArrayHasKey('entries', $export['sources']);
        $this->assertArrayHasKey('fluid', $export['sources']);
        $this->assertArrayHasKey('grid', $export['sources']);
        $this->assertArrayHasKey('members', $export['sources']);
        $this->assertArrayHasKey('sql', $export['sources']);
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testExportLayerFormatsRegistered(Provider $addon): Provider
    {
        $export = $addon->get('export');
        $this->assertArrayHasKey('csv', $export['formats']);
        $this->assertArrayHasKey('json', $export['formats']);
        $this->assertArrayHasKey('xlsx', $export['formats']);
        $this->assertArrayHasKey('xml', $export['formats']);
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testExportLayerModifiersRegistered(Provider $addon): Provider
    {
        $export = $addon->get('export');
        $this->assertArrayHasKey('ee_date', $export['modifiers']);
        $this->assertArrayHasKey('ee_decrypt', $export['modifiers']);
        $this->assertArrayHasKey('replace_with', $export['modifiers']);
        $this->assertArrayHasKey('uc_first', $export['modifiers']);
        $this->assertArrayHasKey('uc_words', $export['modifiers']);
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testExportLayerOutputsRegistered(Provider $addon): Provider
    {
        $export = $addon->get('export');
        $this->assertArrayHasKey('download', $export['outputs']);
        $this->assertArrayHasKey('local', $export['outputs']);
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testExportLayerFieldsRegistered(Provider $addon): Provider
    {
        $export = $addon->get('export');
        $this->assertArrayHasKey('date', $export['fields']);
        $this->assertArrayHasKey('file', $export['fields']);
        $this->assertArrayHasKey('relationship', $export['fields']);
        $this->assertArrayHasKey('grid', $export['fields']);
        $this->assertArrayHasKey('fluid_field', $export['fields']);
        return $addon;
    }

    /**
     * @depends testAuthorValue
     */
    public function testModelRegistered(Provider $addon): Provider
    {
        $models = $addon->get('models');
        $this->assertArrayHasKey('ExportConfiguration', $models);
        return $addon;
    }
}
