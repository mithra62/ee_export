<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Traits\SearchFilterTrait;
use Mithra62\UnitTests\TestCase;

class SearchFilterTraitTest extends TestCase
{
    protected function setUp(): void
    {
        // ee('Model')->get('ChannelEntry')->filter(...) caches custom-field
        // metadata via ee()->session internally (Select::getCustomFields()).
        // The unit_tests CLI bootstrap doesn't load the session library by
        // default (it's only needed by some field types/model paths) — real
        // CP routes and template tags always run inside a full HTTP request
        // where session is already loaded, but this CLI test harness needs
        // it loaded explicitly. Same pattern EE core's own CLI commands use,
        // e.g. ExpressionEngine\Cli\Commands\CommandSyncReindex::handle().
        ee()->load->library('session');
    }

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

            public function apply($query, array $search, int $channel_id = 0): void
            {
                $this->applySearchFilters($query, $search, $channel_id);
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

    public function testApplySearchFiltersCombinesCoreFiltersWithAnd(): void
    {
        $query = $this->query();
        $this->consumer()->apply($query, ['title' => 'Hello', 'status' => 'open']);

        $sql = $query->_compile_select();
        $this->assertStringContainsString('AND', $sql);
    }

    /**
     * Custom-field matching is delegated to the ChannelEntry model (see
     * SearchFilterTrait docblock), so this exercises it against a real
     * channel/field in this dev sandbox rather than mocking the model.
     * Skips gracefully if no channel with at least one custom field exists.
     */
    private function firstRealChannelField(): ?array
    {
        // 'products' is a real channel with custom fields in this dev sandbox
        // (confirmed via exp_channels / exp_channels_channel_fields).
        $channel_id = ee('export:EntryService')->getChannelId('products');
        if (!$channel_id) {
            return null;
        }

        $fields = ee('export:EntryService')->getChannelFields($channel_id);
        if (empty($fields)) {
            return null;
        }

        $field_id = array_key_first($fields);
        return [$channel_id, $field_id, $fields[$field_id]['field_name']];
    }

    public function testApplySearchFiltersResolvesCustomFieldViaChannelEntryModel(): void
    {
        $real = $this->firstRealChannelField();
        if ($real === null) {
            $this->markTestSkipped('No channel with a custom field available in this sandbox.');
        }
        [$channel_id, $field_id, $field_name] = $real;

        $query = $this->query();
        $this->consumer([$field_id => ['field_name' => $field_name]])
            ->apply($query, [$field_name => 'a-value-extremely-unlikely-to-match-xyz123'], $channel_id);

        // No entries can match that value — the trait must force zero rows
        // rather than silently falling back to the unfiltered set.
        $sql = $query->_compile_select();
        $this->assertMatchesRegularExpression('/channel_titles`\.`entry_id`\s*=\s*-1/', $sql);
    }

    public function testApplySearchFiltersDoesNotJoinChannelDataDirectly(): void
    {
        // Custom-field filtering must never reintroduce a manual JOIN against
        // channel_data — that's exactly the column-ambiguity bug this rewrite
        // fixes. Matching is resolved entirely through the ChannelEntry model.
        $real = $this->firstRealChannelField();
        if ($real === null) {
            $this->markTestSkipped('No channel with a custom field available in this sandbox.');
        }
        [$channel_id, $field_id, $field_name] = $real;

        $query = $this->query();
        $this->consumer([$field_id => ['field_name' => $field_name]])
            ->apply($query, [$field_name => 'a-value-extremely-unlikely-to-match-xyz123'], $channel_id);

        $sql = $query->_compile_select();
        $this->assertStringNotContainsString('JOIN', $sql);
    }
}
