<?php

namespace Mithra62\Export\Tests;

use ExpressionEngine\Service\Addon\Mcp;
use Mithra62\UnitTests\TestCase;

class McpTest extends TestCase
{
    public function testMcpFileExists(): void
    {
        $this->assertNotNull(realpath(PATH_THIRD . 'export/mcp.export.php'));
    }

    public function testMcpObjectExists(): void
    {
        require_once PATH_THIRD . 'export/mcp.export.php';
        $this->assertTrue(class_exists('Export_mcp'));
    }

    public function testMcpIsMcpInstance(): void
    {
        require_once PATH_THIRD . 'export/mcp.export.php';
        $this->assertInstanceOf(Mcp::class, new \Export_mcp());
    }
}
