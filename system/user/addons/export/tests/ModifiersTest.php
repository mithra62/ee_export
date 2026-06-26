<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Modifiers\EeDate;
use Mithra62\Export\Modifiers\ReplaceWith;
use Mithra62\Export\Modifiers\UcFirst;
use Mithra62\Export\Modifiers\UcWords;
use Mithra62\UnitTests\TestCase;

class ModifiersTest extends TestCase
{
    // ── UcFirst ───────────────────────────────────────────────────────────────

    public function testUcFirstLowercaseString(): void
    {
        $this->assertEquals('Hello world', (new UcFirst())->process('hello world'));
    }

    public function testUcFirstEmptyString(): void
    {
        $this->assertEquals('', (new UcFirst())->process(''));
    }

    public function testUcFirstAlreadyCapitalized(): void
    {
        $this->assertEquals('HELLO', (new UcFirst())->process('HELLO'));
    }

    public function testUcFirstSingleWord(): void
    {
        $this->assertEquals('Export', (new UcFirst())->process('export'));
    }

    // ── UcWords ───────────────────────────────────────────────────────────────

    public function testUcWordsLowercaseString(): void
    {
        $this->assertEquals('Hello World', (new UcWords())->process('hello world'));
    }

    public function testUcWordsEmptyString(): void
    {
        $this->assertEquals('', (new UcWords())->process(''));
    }

    public function testUcWordsMultipleWords(): void
    {
        $this->assertEquals('Export Add On', (new UcWords())->process('export add on'));
    }

    // ── ReplaceWith ───────────────────────────────────────────────────────────

    public function testReplaceWithDefaultReturnsNa(): void
    {
        $this->assertEquals('N/A', (new ReplaceWith())->process('anything'));
    }

    public function testReplaceWithDefaultOnEmptyString(): void
    {
        $this->assertEquals('N/A', (new ReplaceWith())->process(''));
    }

    public function testReplaceWithDefaultOnNull(): void
    {
        $this->assertEquals('N/A', (new ReplaceWith())->process(null));
    }

    public function testReplaceWithCustomParam(): void
    {
        $m = new ReplaceWith();
        $m->setParams(['REDACTED']);
        $this->assertEquals('REDACTED', $m->process('secret value'));
    }

    public function testReplaceWithCustomParamIgnoresInput(): void
    {
        $m = new ReplaceWith();
        $m->setParams(['***']);
        $this->assertEquals('***', $m->process('one'));
        $this->assertEquals('***', $m->process('two'));
    }

    // ── EeDate ────────────────────────────────────────────────────────────────

    public function testEeDateReturnsValueUnchangedWhenNoFormat(): void
    {
        $m = new EeDate();
        $ts = mktime(0, 0, 0, 1, 15, 2024);
        $this->assertEquals($ts, $m->process($ts));
    }

    public function testEeDateReturnsValueUnchangedWhenValueIsZero(): void
    {
        $m = new EeDate();
        $m->setParams(['%Y-%m-%d']);
        $this->assertEquals(0, $m->process(0));
    }

    public function testEeDateFormatsTimestamp(): void
    {
        $m = new EeDate();
        $m->setParams(['%Y-%m-%d']);
        $ts = mktime(0, 0, 0, 1, 15, 2024);
        $result = $m->process($ts);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
        $this->assertStringContainsString('2024', $result);
    }
}
