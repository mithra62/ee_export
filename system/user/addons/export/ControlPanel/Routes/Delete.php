<?php

namespace Mithra62\Export\ControlPanel\Routes;

use Mithra62\Export\Forms\DeleteExport;

/**
 * Delete — confirmation page before removing a saved Export configuration.
 *
 * GET:  renders a confirmation form with a yes/no toggle
 * POST: deletes only when the 'confirm' field is submitted as 'y'
 *
 * URL: addons/settings/export/delete/{id}
 */
class Delete extends AbstractRoute
{
    protected $route_path = 'delete';
    protected $cp_page_title = 'export_delete_heading';

    public function process($id = false)
    {
        $this->requireSuperAdmin();

        if (!$id) {
            ee()->functions->redirect($this->url('index'));
        }

        $config = ee('Model')
            ->get('export:ExportConfiguration')
            ->filter('id', (int)$id)
            ->filter('site_id', (int)ee()->config->item('site_id'))
            ->first();

        if (!$config) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle(lang('export_err_not_found'))
                ->defer();

            ee()->functions->redirect($this->url('index'));
        }

        // ── POST: confirmed deletion ──────────────────────────────────────────

        if (!empty($_POST) && ee()->input->post('confirm') === 'y') {
            $name = $config->name;
            $config->delete();

            ee('CP/Alert')->makeInline('shared-form')
                ->asSuccess()
                ->withTitle(lang('export_deleted_success'))
                ->addToBody(sprintf(lang('export_deleted_body'), $name))
                ->defer();

            ee()->functions->redirect($this->url('index'));
        }

        // ── GET (or unconfirmed POST): show the confirmation form ─────────────

        $form = new DeleteExport;

        $vars = $form->generate();
        $vars['cp_page_title'] = lang('export_delete_heading') . ': ' . $config->name;
        $vars['base_url'] = $this->url('delete/' . $config->id);
        $vars['save_btn_text'] = lang('export_delete_btn');
        $vars['save_btn_text_working'] = lang('export_deleting');

        $this->setHeading(lang('export_delete_heading'));
        $this->addBreadcrumb($this->url('delete/' . $config->id), lang('export_delete_heading'));
        $this->setView('delete', $vars);

        return $this;
    }
}
