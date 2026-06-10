<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Plugins\AbstractSource;
use Mithra62\UnitTests\TestCase;

class AbstractSourceCleanFieldsTest extends TestCase
{
    private function makeSource(): AbstractSource
    {
        return new class extends AbstractSource {
            public function compile(): AbstractSource { return $this; }
        };
    }

    public function testReturnsAllWhenNoWhitelistOrBlacklist(): void
    {
        $src = $this->makeSource();
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $this->assertEquals($data, $src->cleanFields($data));
    }

    public function testWhitelistFromArray(): void
    {
        $src = $this->makeSource();
        $src->setOptions(['fields' => ['a', 'c']]);
        $result = $src->cleanFields(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertEquals(['a' => 1, 'c' => 3], $result);
    }

    public function testWhitelistFromPipeString(): void
    {
        $src = $this->makeSource();
        $src->setOptions(['fields' => 'a|c']);
        $result = $src->cleanFields(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertEquals(['a' => 1, 'c' => 3], $result);
    }

    public function testWhitelistPreservesDeclarationOrder(): void
    {
        $src = $this->makeSource();
        $src->setOptions(['fields' => ['c', 'a']]);
        $result = $src->cleanFields(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertEquals(['c', 'a'], array_keys($result));
    }

    public function testWhitelistSilentlyIgnoresMissingColumns(): void
    {
        $src = $this->makeSource();
        $src->setOptions(['fields' => ['a', 'z']]);
        $result = $src->cleanFields(['a' => 1, 'b' => 2]);
        $this->assertEquals(['a' => 1], $result);
    }

    public function testExcludeFromArray(): void
    {
        $src = $this->makeSource();
        $src->setOptions(['exclude' => ['b']]);
        $result = $src->cleanFields(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertEquals(['a' => 1, 'c' => 3], $result);
    }

    public function testExcludeFromPipeString(): void
    {
        $src = $this->makeSource();
        $src->setOptions(['exclude' => 'b|c']);
        $result = $src->cleanFields(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertEquals(['a' => 1], $result);
    }

    public function testWhitelistTakesPriorityOverExclude(): void
    {
        $src = $this->makeSource();
        $src->setOptions(['fields' => ['a'], 'exclude' => ['a']]);
        $result = $src->cleanFields(['a' => 1, 'b' => 2]);
        $this->assertEquals(['a' => 1], $result);
    }

    public function testEmptyArrayWhitelistIsIgnored(): void
    {
        $src = $this->makeSource();
        $src->setOptions(['fields' => []]);
        $data = ['a' => 1, 'b' => 2];
        $this->assertEquals($data, $src->cleanFields($data));
    }

    public function testTrimsSpacesInPipeString(): void
    {
        $src = $this->makeSource();
        $src->setOptions(['fields' => ' a | c ']);
        $result = $src->cleanFields(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertArrayHasKey('a', $result);
        $this->assertArrayHasKey('c', $result);
        $this->assertArrayNotHasKey('b', $result);
    }
}
