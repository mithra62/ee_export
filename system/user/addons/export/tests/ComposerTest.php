<?php

namespace Mithra62\Export\Tests;

use Mithra62\UnitTests\TestCase;

class ComposerTest extends TestCase
{
    public function testComposerFileExists(): void
    {
        $this->assertNotNull(realpath(PATH_THIRD . 'export/composer.json'));
    }

    public function testComposerIsValidJson(): array
    {
        $path = realpath(PATH_THIRD . 'export/composer.json');
        $composer = json_decode(file_get_contents($path), true);
        $this->assertIsArray($composer);
        return $composer;
    }

    /**
     * @depends testComposerIsValidJson
     */
    public function testOpenSpoutRequired(array $composer): array
    {
        $this->assertArrayHasKey('openspout/openspout', $composer['require']);
        $this->assertEquals('^4.0', $composer['require']['openspout/openspout']);
        return $composer;
    }

    public function testVendorDirectoryExists(): void
    {
        $this->assertTrue(is_dir(PATH_THIRD . 'export/vendor'));
    }

    public function testAutoloadFileExists(): void
    {
        $this->assertNotNull(realpath(PATH_THIRD . 'export/vendor/autoload.php'));
    }
}
