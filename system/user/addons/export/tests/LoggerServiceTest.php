<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Services\LoggerService;
use Mithra62\UnitTests\TestCase;

class LoggerServiceTest extends TestCase
{
    private LoggerService $service;

    protected function setUp(): void
    {
        $this->service = new LoggerService();
    }

    public function testInstantiates(): void
    {
        $this->assertInstanceOf(LoggerService::class, $this->service);
    }

    public function testFormatContainsLevel(): void
    {
        $result = $this->service->format('error', 'test message');
        $this->assertStringContainsString('error', $result);
    }

    public function testFormatContainsMessage(): void
    {
        $result = $this->service->format('info', 'hello export');
        $this->assertStringContainsString('hello export', $result);
    }

    public function testFormatIsNonEmptyString(): void
    {
        $result = $this->service->format('notice', 'msg');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testFormatWithContextIncludesJson(): void
    {
        $result = $this->service->format('debug', 'msg', ['key' => 'val']);
        $this->assertStringContainsString('"key"', $result);
        $this->assertStringContainsString('"val"', $result);
    }

    public function testFormatWithoutContextOmitsJsonBrace(): void
    {
        $result = $this->service->format('notice', 'no context here');
        $this->assertStringNotContainsString('{', $result);
    }

    public function testShouldLogReturnsTrueForError(): void
    {
        $this->assertTrue($this->service->shouldLog('error'));
    }

    public function testShouldLogReturnsTrueForNotice(): void
    {
        $this->assertTrue($this->service->shouldLog('notice'));
    }

    public function testShouldLogReturnsTrueForWarning(): void
    {
        $this->assertTrue($this->service->shouldLog('warning'));
    }

    public function testShouldLogReturnsFalseForDebug(): void
    {
        $this->assertFalse($this->service->shouldLog('debug'));
    }

    public function testShouldLogReturnsFalseForInfo(): void
    {
        $this->assertFalse($this->service->shouldLog('info'));
    }
}
