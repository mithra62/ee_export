<?php

namespace Mithra62\Export\ControlPanel\Routes;

/**
 * Run — execute a saved Export configuration from the CP.
 *
 * For 'download' output: the Download output plugin sets Content-Disposition
 * headers and calls exit(), so the CP HTML wrapper is never rendered.
 *
 * For 'local' output: build() completes normally; we show a success alert and
 * redirect back to the Index.
 *
 * URL: addons/settings/export/run/{id}
 */
class Run extends AbstractRoute
{
    protected $route_path    = 'run';
    protected $cp_page_title = 'export_run_heading';

    public function process($id = false)
    {
        $config = ee('Model')
            ->get('export:ExportConfiguration')
            ->filter('id', (int) $id)
            ->filter('site_id', (int) ee()->config->item('site_id'))
            ->first();

        if (! $config) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle(lang('export_err_not_found'))
                ->defer();

            ee()->functions->redirect($this->url('index'));
        }

        $params = ee('export:CpService')->buildParamsFromSettings(
            $config->source,
            $config->getSettings()
        );

        $export = ee('export:ExportService')->setParameters($params);

        if (! $export->validate()) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle(lang('export_err_heading'))
                ->addToBody(lang('export_err_fix_below'))
                ->defer();

            ee()->functions->redirect($this->url('index'));
            return;
        }

        try {
            // For 'download' output this triggers headers + exit.
            // For 'local' output execution continues past this line.
            $export->build();
        } catch (\Throwable $e) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle(lang('export_run_failed'))
                ->addToBody($e->getMessage())
                ->defer();

            ee()->functions->redirect($this->url('index'));
        }

        // Reached only for local (non-exit) output
        ee('CP/Alert')->makeInline('shared-form')
            ->asSuccess()
            ->withTitle(lang('export_run_success'))
            ->defer();

        ee()->functions->redirect($this->url('index'));
    }
}
