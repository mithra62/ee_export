<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Services\ModifiersService;
use Mithra62\Export\Services\ParamsService;
use Mithra62\UnitTests\TestCase;

class ModifiersServiceTest extends TestCase
{
    private ModifiersService $service;
    private ParamsService $params;

    protected function setUp(): void
    {
        $this->params  = new ParamsService();
        $this->service = new ModifiersService();
        $this->service->setParams($this->params);
    }

    public function testReturnsRowsUnchangedWhenNoModifyParams(): void
    {
        $rows = [['name' => 'hello', 'age' => 25]];
        $this->assertEquals($rows, $this->service->processChunk($rows));
    }

    public function testAppliesUcFirst(): void
    {
        $this->params->setParams(['modify:name' => 'uc_first']);
        $result = $this->service->processChunk([['name' => 'hello world']]);
        $this->assertEquals('Hello world', $result[0]['name']);
    }

    public function testAppliesUcWords(): void
    {
        $this->params->setParams(['modify:name' => 'uc_words']);
        $result = $this->service->processChunk([['name' => 'hello world']]);
        $this->assertEquals('Hello World', $result[0]['name']);
    }

    public function testAppliesReplaceWith(): void
    {
        $this->params->setParams(['modify:name' => 'replace_with[REDACTED]']);
        $result = $this->service->processChunk([['name' => 'secret']]);
        $this->assertEquals('REDACTED', $result[0]['name']);
    }

    public function testChainsMultipleModifiers(): void
    {
        $this->params->setParams(['modify:title' => 'replace_with[hello world]|uc_words']);
        $result = $this->service->processChunk([['title' => 'anything']]);
        $this->assertEquals('Hello World', $result[0]['title']);
    }

    public function testSkipsFieldNotPresentInRow(): void
    {
        $this->params->setParams(['modify:missing_field' => 'uc_first']);
        $rows = [['name' => 'hello']];
        $result = $this->service->processChunk($rows);
        $this->assertEquals('hello', $result[0]['name']);
    }

    public function testAppliesModifierToAllRows(): void
    {
        $this->params->setParams(['modify:title' => 'uc_first']);
        $rows = [['title' => 'a'], ['title' => 'b']];
        $result = $this->service->processChunk($rows);
        $this->assertEquals('A', $result[0]['title']);
        $this->assertEquals('B', $result[1]['title']);
    }
}
