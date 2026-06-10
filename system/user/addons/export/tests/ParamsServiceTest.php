<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Services\ParamsService;
use Mithra62\UnitTests\TestCase;

class ParamsServiceTest extends TestCase
{
    private ParamsService $params;

    protected function setUp(): void
    {
        $this->params = new ParamsService();
    }

    public function testInstantiates(): void
    {
        $this->assertInstanceOf(ParamsService::class, $this->params);
    }

    public function testGetReturnsNullDefaultWhenKeyMissing(): void
    {
        $this->assertNull($this->params->get('missing_key'));
    }

    public function testGetReturnsProvidedDefaultWhenKeyMissing(): void
    {
        $this->assertEquals('fallback', $this->params->get('missing_key', 'fallback'));
    }

    public function testSetAndGet(): void
    {
        $this->params->set('foo', 'bar');
        $this->assertEquals('bar', $this->params->get('foo'));
    }

    public function testSetReturnsFluent(): void
    {
        $this->assertInstanceOf(ParamsService::class, $this->params->set('x', 1));
    }

    public function testSetParams(): void
    {
        $this->params->setParams(['a' => 1, 'b' => 2]);
        $this->assertEquals(['a' => 1, 'b' => 2], $this->params->getAllParams());
    }

    public function testSetParamsReturnsFluent(): void
    {
        $this->assertInstanceOf(ParamsService::class, $this->params->setParams([]));
    }

    public function testGetAllParams(): void
    {
        $this->params->setParams(['k' => 'v']);
        $this->assertEquals('v', $this->params->getAllParams()['k']);
    }

    public function testGetDomainParamsStripsPrefix(): void
    {
        $this->params->setParams(['source:channel' => 'news', 'format' => 'csv']);
        $domain = $this->params->getDomainParams('source', false);
        $this->assertArrayHasKey('channel', $domain);
        $this->assertArrayNotHasKey('source:channel', $domain);
        $this->assertEquals('news', $domain['channel']);
    }

    public function testGetDomainParamsExcludesNonPrefixedWhenFalse(): void
    {
        $this->params->setParams(['source:channel' => 'news', 'format' => 'csv']);
        $domain = $this->params->getDomainParams('source', false);
        $this->assertArrayNotHasKey('format', $domain);
    }

    public function testGetDomainParamsIncludesAllWhenTrue(): void
    {
        $this->params->setParams(['source:channel' => 'news', 'format' => 'csv']);
        $domain = $this->params->getDomainParams('source', true);
        $this->assertArrayHasKey('channel', $domain);
        $this->assertArrayHasKey('format', $domain);
    }

    public function testGetDomainParamsReturnsEmptyWhenNoPrefixedKeys(): void
    {
        $this->params->setParams(['other' => 'val']);
        $domain = $this->params->getDomainParams('format', false);
        $this->assertEmpty($domain);
    }

    public function testSetParamsReplacesExistingParams(): void
    {
        $this->params->setParams(['a' => 1]);
        $this->params->setParams(['b' => 2]);
        $this->assertNull($this->params->get('a'));
        $this->assertEquals(2, $this->params->get('b'));
    }
}
