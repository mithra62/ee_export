<?php

namespace Mithra62\Export\ControlPanel\Routes;

/**
 * Edit — edit an existing saved Export configuration.
 *
 * GET:  pre-fill form from stored settings
 * POST: validate → update → redirect to Index with success alert
 *
 * URL: addons/settings/export/edit/{id}
 */
class Edit extends AbstractRoute
{
    protected $route_path    = 'edit';
    protected $cp_page_title = 'export_edit_heading';

    public function process($id = false)
    {
        $config = $this->loadConfig((int) $id);

        $this->setHeading(lang('export_edit_heading'));
        $this->addBreadcrumb($this->url('edit/' . $id), lang('export_edit_heading'));

        if (! empty($_POST)) {
            return $this->handlePost($config);
        }

        return $this->renderForm($config, $config->getSettings(), $config->source);
    }

    // ── POST handler ──────────────────────────────────────────────────────────

    protected function handlePost($config)
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
            return $this->renderForm($config, $settings, $source);
        }

        // ── Persist ───────────────────────────────────────────────────────────
        $settings = ee('export:CpService')->postToSettings($post, $source);

        $config->name       = trim($post['name'] ?? '');
        $config->source     = $source;
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

    protected function renderForm($config, array $settings, string $source)
    {
        // On a GET request $settings comes from $config->getSettings(), which is
        // the raw JSON blob — it never contains 'name' (a separate DB column).
        // On a POST validation-failure re-render, postToSettings() now includes
        // 'name', so the isset check leaves the submitted value intact.
        if (! isset($settings['name'])) {
            $settings['name'] = $config->name;
        }

        $form = ee('export:CpService')->buildForm($settings, $source);
        $form->setCpPageTitle(lang('export_edit_heading') . ': ' . $config->name)
             ->setBaseUrl($this->url('edit/' . $config->id))
             ->setSaveBtnText(lang('export_save'))
             ->setSaveBtnTextWorking(lang('export_saving'));

        $this->setView('form', $form->toArray());

        return $this;
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    protected function loadConfig(int $id)
    {
        $config = ee('Model')
            ->get('export:ExportConfiguration')
            ->filter('id', $id)
            ->filter('site_id', (int) ee()->config->item('site_id'))
            ->first();

        if (! $config) {
            show_error(lang('export_err_not_found'), 404);
        }

        return $config;
    }
}
