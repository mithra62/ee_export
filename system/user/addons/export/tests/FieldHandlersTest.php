<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Fields\Date;
use Mithra62\Export\Fields\Grid;
use Mithra62\Export\Fields\Relationship;
use Mithra62\UnitTests\TestCase;

class FieldHandlersTest extends TestCase
{
    // ── Date ──────────────────────────────────────────────────────────────────

    public function testDateProcessReturnsInt(): void
    {
        $this->assertSame(1234567890, (new Date())->process('1234567890', [], 1));
    }

    public function testDateProcessNullReturnsZero(): void
    {
        $this->assertSame(0, (new Date())->process(null, [], 1));
    }

    public function testDateProcessEmptyStringReturnsZero(): void
    {
        $this->assertSame(0, (new Date())->process('', [], 1));
    }

    public function testDateProcessIntegerPassthrough(): void
    {
        $this->assertSame(1700000000, (new Date())->process(1700000000, [], 1));
    }

    // ── Relationship ──────────────────────────────────────────────────────────

    public function testRelationshipReturnsEmptyArrayWithNoContext(): void
    {
        $this->assertEquals([], (new Relationship())->process(null, ['field_id' => 5], 10));
    }

    public function testRelationshipReturnsEmptyArrayWhenNoChildIds(): void
    {
        $context = ['rel_data' => [10 => [5 => []]], 'rel_cache' => []];
        $this->assertEquals([], (new Relationship())->process(null, ['field_id' => 5], 10, $context));
    }

    public function testRelationshipReturnsEntryIdWhenNotCached(): void
    {
        $context = ['rel_data' => [10 => [5 => [99]]], 'rel_cache' => []];
        $result = (new Relationship())->process(null, ['field_id' => 5], 10, $context);
        $this->assertEquals([['entry_id' => 99]], $result);
    }

    public function testRelationshipReturnsTitleWhenCached(): void
    {
        $context = [
            'rel_data'  => [10 => [5 => [99]]],
            'rel_cache' => [99 => ['title' => 'My Entry']],
        ];
        $result = (new Relationship())->process(null, ['field_id' => 5], 10, $context);
        $this->assertEquals([['entry_id' => 99, 'title' => 'My Entry']], $result);
    }

    public function testRelationshipHandlesMultipleRelated(): void
    {
        $context = [
            'rel_data'  => [10 => [5 => [99, 100]]],
            'rel_cache' => [99 => ['title' => 'A'], 100 => ['title' => 'B']],
        ];
        $result = (new Relationship())->process(null, ['field_id' => 5], 10, $context);
        $this->assertCount(2, $result);
        $this->assertEquals('A', $result[0]['title']);
        $this->assertEquals('B', $result[1]['title']);
    }

    // ── Grid ──────────────────────────────────────────────────────────────────

    public function testGridReturnsEmptyArrayWithNoContext(): void
    {
        $this->assertEquals([], (new Grid())->process(null, ['field_id' => 3], 10));
    }

    public function testGridReturnsEmptyArrayWhenNoRows(): void
    {
        $context = ['grid_data' => [3 => [10 => []]], 'grid_columns' => [], 'rel_cache' => []];
        $this->assertEquals([], (new Grid())->process(null, ['field_id' => 3], 10, $context));
    }

    public function testGridMapsColumnNamesToValues(): void
    {
        $context = [
            'grid_data'    => [3 => [10 => [['col_id_1' => 'hello', 'col_id_2' => 'world']]]],
            'grid_columns' => [3 => [
                1 => ['col_name' => 'title', 'col_type' => 'text'],
                2 => ['col_name' => 'body',  'col_type' => 'text'],
            ]],
            'rel_cache' => [],
        ];
        $result = (new Grid())->process(null, ['field_id' => 3], 10, $context);
        $this->assertCount(1, $result);
        $this->assertEquals('hello', $result[0]['title']);
        $this->assertEquals('world', $result[0]['body']);
    }

    public function testGridResolvesRelationshipColumn(): void
    {
        $context = [
            'grid_data'    => [3 => [10 => [['col_id_1' => '55']]]],
            'grid_columns' => [3 => [
                1 => ['col_name' => 'related', 'col_type' => 'relationship'],
            ]],
            'rel_cache' => [55 => ['title' => 'Related Entry']],
        ];
        $result = (new Grid())->process(null, ['field_id' => 3], 10, $context);
        $this->assertEquals(['entry_id' => 55, 'title' => 'Related Entry'], $result[0]['related']);
    }

    public function testGridHandlesMultipleRows(): void
    {
        $context = [
            'grid_data'    => [3 => [10 => [
                ['col_id_1' => 'row1'],
                ['col_id_1' => 'row2'],
            ]]],
            'grid_columns' => [3 => [
                1 => ['col_name' => 'val', 'col_type' => 'text'],
            ]],
            'rel_cache' => [],
        ];
        $result = (new Grid())->process(null, ['field_id' => 3], 10, $context);
        $this->assertCount(2, $result);
        $this->assertEquals('row1', $result[0]['val']);
        $this->assertEquals('row2', $result[1]['val']);
    }
}
