<?php

namespace Mithra62\Export\ControlPanel\Routes;

/**
 * Create — create a new saved Export configuration.
 *
 * GET:  render blank form
 * POST: validate → save → redirect to Index with success alert
 *
 * URL: addons/settings/export/create
 */
class Create extends AbstractRoute
{
    protected $route_path    = 'create';
    protected $cp_page_title = 'export_create_heading';

    public function process($id = false)
    {
        $this->setHeading(lang('export_create_heading'));
        $this->addBreadcrumb($this->url('create'), lang('export_create_heading'));

        if (! empty($_POST)) {
            return $this->handlePost();
        }

        return $this->renderForm();
    }

    // ── POST handler ──────────────────────────────────────────────────────────

    protected function handlePost()
    {
        $post   = $_POST;
        $source = trim(ee('Request')->post('source', ''));

        if (! $this->validate($post)) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle(lang('export_err_heading'))
                ->addToBody(lang('export_err_fix_below'))
                ->now();

            $settings = ee('export:CpService')->postToSettings($post, $source);
            return $this->renderForm($settings, $source);
        }

        // ── Persist ───────────────────────────────────────────────────────────
        $settings = ee('export:CpService')->postToSettings($post, $source);

        $config = ee('Model')->make('export:ExportConfiguration');
        $config->site_id    = (int) ee()->config->item('site_id');
        $config->name       = trim($post['name'] ?? '');
        $config->source     = $source;
        $config->created_at = time();
        $config->updated_at = time();
        $config->setSettings($settings);
        $config->save();

        ee('CP/Alert')->makeInline('shared-form')
            ->asSuccess()
            ->withTitle(lang('export_saved_success'))
            ->defer();

        ee()->functions->redirect($this->url('index'));
    }

    // ── Form renderer ─────────────────────────────────────────────────────────

    protected function renderForm(array $settings = [], string $source = 'entries')
    {
        $form = ee('export:CpService')->buildForm($settings, $source);
        $form->setCpPageTitle(lang('export_create_heading'))
             ->setBaseUrl($this->url('create'))
             ->setSaveBtnText(lang('export_save'))
             ->setSaveBtnTextWorking(lang('export_saving'));

        $this->setView('form', $form->toArray());

        return $this;
    }
}
