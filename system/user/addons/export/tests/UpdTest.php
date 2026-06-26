<?php

namespace Mithra62\Export\Tests;

use ExpressionEngine\Service\Addon\Installer;
use Mithra62\UnitTests\TestCase;

class UpdTest extends TestCase
{
    public function testUpdFileExists(): void
    {
        $this->assertNotNull(realpath(PATH_THIRD . 'export/upd.export.php'));
    }

    public function testUpdObjectExists(): void
    {
        require_once PATH_THIRD . 'export/upd.export.php';
        $this->assertTrue(class_exists('Export_upd'));
    }

    public function testHasCpBackendPropertyExists(): \Export_upd
    {
        require_once PATH_THIRD . 'export/upd.export.php';
        $obj = new \Export_upd();
        $this->assertObjectHasAttribute('has_cp_backend', $obj);
        return $obj;
    }

    /**
     * @depends testHasCpBackendPropertyExists
     */
    public function testCpBackendPropertyValue(\Export_upd $obj): \Export_upd
    {
        $this->assertEquals('y', $obj->has_cp_backend);
        return $obj;
    }

    /**
     * @depends testHasCpBackendPropertyExists
     */
    public function testPublishFieldsPropertyExists(\Export_upd $obj): \Export_upd
    {
        $this->assertObjectHasAttribute('has_publish_fields', $obj);
        return $obj;
    }

    /**
     * @depends testHasCpBackendPropertyExists
     */
    public function testPublishFieldsPropertyValue(\Export_upd $obj): \Export_upd
    {
        $this->assertEquals('n', $obj->has_publish_fields);
        return $obj;
    }

    /**
     * @depends testHasCpBackendPropertyExists
     */
    public function testIsInstallerInstance(\Export_upd $obj): \Export_upd
    {
        $this->assertInstanceOf(Installer::class, $obj);
        return $obj;
    }

    /**
     * @depends testIsInstallerInstance
     */
    public function testInstallMethodExists(\Export_upd $obj): void
    {
        $this->assertTrue(method_exists($obj, 'install'));
    }

    /**
     * @depends testIsInstallerInstance
     */
    public function testUninstallMethodExists(\Export_upd $obj): void
    {
        $this->assertTrue(method_exists($obj, 'uninstall'));
    }

    /**
     * @depends testIsInstallerInstance
     */
    public function testUpdateMethodExists(\Export_upd $obj): void
    {
        $this->assertTrue(method_exists($obj, 'update'));
    }
}
