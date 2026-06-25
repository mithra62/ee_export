<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Traits\SearchFilterTrait;
use Mithra62\UnitTests\TestCase;

class SearchFilterTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        // _compile_select() does not reset builder state the way get() does —
        // reset explicitly so accumulated select/from/join state from one test
        // never leaks into the next test's query.
        ee()->db->_reset_select();
    }

    private function consumer(array $channel_fields = [])
    {
        return new class ($channel_fields) {
            use SearchFilterTrait;

            public array $channel_fields;

            public function __construct(array $channel_fields)
            {
                $this->channel_fields = $channel_fields;
            }

            public function apply($query, array $search): void
            {
                $this->applySearchFilters($query, $search);
            }
        };
    }

    private function query()
    {
        return ee()->db->select('channel_titles.entry_id')->from('channel_titles');
    }

    public function testGetChannelTitlesColumnsReturnsRealColumns(): void
    {
        $columns = ee('export:EntryService')->getChannelTitlesColumns();
        $this->assertArrayHasKey('entry_id', $columns);
        $this->assertArrayHasKey('title', $columns);
    }

    public function testApplySearchFiltersMatchesCoreColumn(): void
    {
        $query = $this->query();
        $this->consumer()->apply($query, ['title' => 'Hello World']);

        $sql = $query->_compile_select();
        $this->assertStringContainsString('channel_titles`.`title`', $sql);
        $this->assertStringContainsString('Hello World', $sql);
    }

    public function testApplySearchFiltersSkipsEmptyValues(): void
    {
        $query = $this->query();
        $this->consumer()->apply($query, ['title' => '']);

        $sql = $query->_compile_select();
        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function testApplySearchFiltersIgnoresUnknownFieldName(): void
    {
        $query = $this->query();
        $this->consumer()->apply($query, ['totally_unknown_field_xyz' => 'value']);

        $sql = $query->_compile_select();
        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function testApplySearchFiltersMatchesCustomFieldAndJoinsChannelData(): void
    {
        $query = $this->query();
        $this->consumer([5 => ['field_name' => 'headline']])
            ->apply($query, ['headline' => 'Featured']);

        $sql = $query->_compile_select();
        $this->assertStringContainsString('channel_data', $sql);
        $this->assertStringContainsString('field_id_5', $sql);
        $this->assertStringContainsString('Featured', $sql);
    }

    public function testApplySearchFiltersCombinesMultipleFiltersWithAnd(): void
    {
        $query = $this->query();
        $this->consumer()->apply($query, ['title' => 'Hello', 'status' => 'open']);

        $sql = $query->_compile_select();
        $this->assertStringContainsString('AND', $sql);
    }
}
