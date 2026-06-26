<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Models\ExportConfiguration;
use Mithra62\UnitTests\TestCase;

class ExportConfigurationModelTest extends TestCase
{
    private ExportConfiguration $model;

    protected function setUp(): void
    {
        $this->model = ee('Model')->make('export:ExportConfiguration');
    }

    public function testMakeReturnsModel(): void
    {
        $this->assertInstanceOf(ExportConfiguration::class, $this->model);
    }

    public function testGetSettingsReturnsEmptyArrayWhenSettingsIsNull(): void
    {
        $this->assertEquals([], $this->model->getSettings());
    }

    public function testGetSettingsDecodesJson(): void
    {
        $this->model->setSettings(['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $this->model->getSettings());
    }

    public function testSetSettingsRoundTrip(): void
    {
        $data = ['source' => 'entries', 'format' => 'csv', 'source:channel' => 'news'];
        $this->model->setSettings($data);
        $this->assertEquals($data, $this->model->getSettings());
    }

    public function testSetSettingsNestedArray(): void
    {
        $data = ['fields' => ['entry_id', 'title', 'status']];
        $this->model->setSettings($data);
        $this->assertEquals(['entry_id', 'title', 'status'], $this->model->getSettings()['fields']);
    }

    public function testGetFormattedCreatedAtReturnsEmptyStringWhenNull(): void
    {
        $this->assertEquals('', $this->model->getFormattedCreatedAt());
    }

    public function testGetFormattedCreatedAtDefaultFormat(): void
    {
        $this->model->setRawProperty('created_at', mktime(0, 0, 0, 1, 15, 2024));
        $result = $this->model->getFormattedCreatedAt();
        $this->assertStringContainsString('2024', $result);
        $this->assertStringContainsString('01', $result);
    }

    public function testGetFormattedCreatedAtCustomFormat(): void
    {
        $this->model->setRawProperty('created_at', mktime(0, 0, 0, 6, 1, 2025));
        $this->assertEquals('2025', $this->model->getFormattedCreatedAt('Y'));
    }
}
