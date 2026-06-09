<?php

namespace Mithra62\Export\ControlPanel\Routes;

/**
 * Delete — remove a saved Export configuration.
 *
 * Expects a POST or GET request with the config ID in the route segment.
 * Redirects to Index with an alert on completion.
 *
 * URL: addons/settings/export/delete/{id}
 */
class Delete extends AbstractRoute
{
    protected $route_path    = 'delete';
    protected $cp_page_title = 'export_delete_heading';

    public function process($id = false)
    {
        $config = ee('Model')
            ->get('export:ExportConfiguration')
            ->filter('id', (int) $id)
            ->filter('site_id', (int) ee()->config->item('site_id'))
            ->first();

        if ($config) {
            $name = $config->name;
            $config->delete();

            ee('CP/Alert')->makeInline('shared-form')
                ->asSuccess()
                ->withTitle(lang('export_deleted_success'))
                ->addToBody(sprintf(lang('export_deleted_body'), $name))
                ->defer();
        }

        ee()->functions->redirect($this->url('index'));
    }
}
