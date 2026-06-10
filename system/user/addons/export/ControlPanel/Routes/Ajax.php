<?php

namespace Mithra62\Export\ControlPanel\Routes;

/**
 * Ajax — JSON endpoints used by the Create/Edit form.
 *
 * POST addons/settings/export/ajax
 *
 * Required POST key: action
 *   'columns' — returns available output column names for a source + channel
 *   'fields'  — returns field names of a given type for a channel (Grid/Fluid field selectors)
 */
class Ajax extends AbstractRoute
{
    protected $route_path = 'ajax';
    protected $cp_page_title = '';

    public function process($id = false)
    {
        $action = ee('Request')->post('action', '');
        $data = [];

        switch ($action) {

            case 'columns':
                $source = ee('Request')->post('source', '');
                $params = $_POST;
                $data = ee('export:CpService')->getColumnsForSource($source, $params);
                break;

            case 'fields':
                $channel_id = (int)ee('Request')->post('channel_id', 0);
                $field_type = ee('Request')->post('field_type', '');
                $data = ee('export:CpService')->getChannelFields($channel_id, $field_type ?: null);
                break;

            default:
                $data = ['error' => 'Unknown action'];
                break;
        }

        ee()->output->send_ajax_response($data);
    }
}
