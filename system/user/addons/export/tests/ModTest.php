<?php

namespace Mithra62\Export\Tests;

use ExpressionEngine\Service\Addon\Module;
use Mithra62\UnitTests\TestCase;

class ModTest extends TestCase
{
    public function testModFileExists(): void
    {
        $this->assertNotNull(realpath(PATH_THIRD . 'export/mod.export.php'));
    }

    public function testModuleObjectExists(): void
    {
        require_once PATH_THIRD . 'export/mod.export.php';
        $this->assertTrue(class_exists('Export'));
    }

    public function testModuleIsModuleInstance(): void
    {
        require_once PATH_THIRD . 'export/mod.export.php';
        $this->assertInstanceOf(Module::class, new \Export());
    }
}
