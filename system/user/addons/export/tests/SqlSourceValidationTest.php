<?php

namespace Mithra62\Export\Tests;

use Mithra62\Export\Sources\Sql;
use Mithra62\UnitTests\TestCase;

class SqlSourceValidationTest extends TestCase
{
    private function sql(string $query): Sql
    {
        $s = new Sql();
        $s->setOptions(['source' => 'sql', 'query' => $query]);
        return $s;
    }

    public function testValidSelectPasses(): void
    {
        $this->assertTrue($this->sql('SELECT * FROM exp_channel_titles')->validate()->isValid());
    }

    public function testValidSelectWithColumnAlias(): void
    {
        $this->assertTrue($this->sql('SELECT entry_id AS id FROM exp_channel_titles')->validate()->isValid());
    }

    public function testUpdateKeywordFails(): void
    {
        $this->assertFalse($this->sql('UPDATE exp_members SET screen_name = "x"')->validate()->isValid());
    }

    public function testInsertKeywordFails(): void
    {
        $this->assertFalse($this->sql('INSERT INTO foo VALUES(1)')->validate()->isValid());
    }

    public function testDeleteKeywordFails(): void
    {
        $this->assertFalse($this->sql('DELETE FROM exp_members WHERE member_id = 1')->validate()->isValid());
    }

    public function testDropKeywordFails(): void
    {
        $this->assertFalse($this->sql('SELECT 1 DROP TABLE exp_members')->validate()->isValid());
    }

    public function testTruncateKeywordFails(): void
    {
        $this->assertFalse($this->sql('SELECT 1 TRUNCATE TABLE foo')->validate()->isValid());
    }

    public function testSemicolonFails(): void
    {
        $this->assertFalse($this->sql('SELECT 1; DROP TABLE exp_members')->validate()->isValid());
    }

    public function testLineCommentStrippedBeforeAnalysis(): void
    {
        $this->assertTrue($this->sql("-- DROP TABLE foo\nSELECT 1")->validate()->isValid());
    }

    public function testBlockCommentStrippedBeforeAnalysis(): void
    {
        $this->assertTrue($this->sql('/* DROP TABLE foo */ SELECT 1')->validate()->isValid());
    }

    public function testMissingQueryFailsRequired(): void
    {
        $s = new Sql();
        $s->setOptions(['source' => 'sql', 'query' => '']);
        $this->assertFalse($s->validate()->isValid());
    }

    public function testNotASelectFails(): void
    {
        $this->assertFalse($this->sql('SHOW TABLES')->validate()->isValid());
    }
}
