<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Output\Download;
use Mithra62\UnitTests\TestCase;

class DownloadOutputValidationTest extends TestCase
{
    public function testShouldDieIsTrue(): void
    {
        $this->assertTrue((new Download())->shouldDie());
    }

    public function testValidationPassesWhenFilenameSet(): void
    {
        $d = new Download();
        $d->setOptions(['output' => 'download', 'filename' => 'export.csv']);
        $this->assertTrue($d->validate()->isValid());
    }

    public function testValidationFailsWhenFilenameEmpty(): void
    {
        $d = new Download();
        $d->setOptions(['output' => 'download', 'filename' => '']);
        $this->assertFalse($d->validate()->isValid());
    }

    public function testValidationFailsWhenFilenameAbsent(): void
    {
        $d = new Download();
        $d->setOptions(['output' => 'download']);
        $this->assertFalse($d->validate()->isValid());
    }
}
