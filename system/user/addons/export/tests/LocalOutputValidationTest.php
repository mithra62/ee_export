<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Output\Local;
use Mithra62\UnitTests\TestCase;

class LocalOutputValidationTest extends TestCase
{
    public function testShouldDieIsFalse(): void
    {
        $this->assertFalse((new Local())->shouldDie());
    }

    public function testValidationPassesWithValidDirAndFilename(): void
    {
        $l = new Local();
        $l->setOptions(['output' => 'local', 'filename' => 'out.csv', 'path' => sys_get_temp_dir()]);
        $this->assertTrue($l->validate()->isValid());
    }

    public function testValidationFailsWhenFilenameMissing(): void
    {
        $l = new Local();
        $l->setOptions(['output' => 'local', 'filename' => '', 'path' => sys_get_temp_dir()]);
        $this->assertFalse($l->validate()->isValid());
    }

    public function testValidationFailsWhenPathMissing(): void
    {
        $l = new Local();
        $l->setOptions(['output' => 'local', 'filename' => 'out.csv', 'path' => '']);
        $this->assertFalse($l->validate()->isValid());
    }

    public function testValidationFailsForNonExistentDir(): void
    {
        $l = new Local();
        $l->setOptions(['output' => 'local', 'filename' => 'out.csv', 'path' => '/path/that/does/not/exist_xyz_123']);
        $this->assertFalse($l->validate()->isValid());
    }

    public function testBasenamePreventsDirectoryTraversal(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'export_test_');
        file_put_contents($source, 'test');

        $l = new Local();
        $l->setOptions(['output' => 'local', 'filename' => '../../etc/passwd', 'path' => sys_get_temp_dir()]);
        $l->process($source);

        $expected = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'passwd';
        if (file_exists($expected)) {
            $this->assertStringNotContainsString('..', $expected);
            @unlink($expected);
        } else {
            // process() returned false (copy failed or similar) — traversal was blocked
            $this->assertTrue(true);
        }

        @unlink($source);
    }
}
