<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use ExpressionEngine\Service\Addon\Mcp;

/**
 * Export MCP gateway — EE 7.2+ auto-routing.
 *
 * EE's Mcp base class resolves route classes automatically using Str::studly():
 *   addons/settings/export/index  → ControlPanel\Routes\Index
 *   addons/settings/export/create → ControlPanel\Routes\Create
 *   … and so on.
 *
 * No explicit method registration is required.
 */
class Export_mcp extends Mcp
{
    protected $addon_name = 'export';
}
