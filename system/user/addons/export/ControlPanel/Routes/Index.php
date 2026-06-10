<?php

namespace Mithra62\Export\ControlPanel\Routes;

use ExpressionEngine\Library\CP\Table;

/**
 * Index — lists all saved Export configurations for the current site.
 *
 * URL: addons/settings/export/index  (also the addon's default CP page)
 */
class Index extends AbstractRoute
{
    protected $route_path    = 'index';
    protected $cp_page_title = 'export_cp_heading';

    public function process($id = false)
    {
        $this->setHeading(lang('export_cp_heading'));

        // ── Load all configs for this site ──────────────────────────────────
        $configs = ee('Model')
            ->get('export:ExportConfiguration')
            ->filter('site_id', (int) ee()->config->item('site_id'))
            ->order('name', 'asc')
            ->all();

        // ── Build table ─────────────────────────────────────────────────────
        $table = ee('CP/Table', [
            'lang_cols'  => true,
            'reorder'    => false,
            'sortable'   => false,
            'class'      => 'export-configs',
        ]);

        $table->setColumns([
            'export_col_name'    => ['sort' => false],
            'export_col_source'  => ['sort' => false],
            'export_col_format'  => ['sort' => false],
            'export_col_output'  => ['sort' => false],
            'export_col_created' => ['sort' => false],
            'manage'             => ['type' => Table::COL_TOOLBAR],
        ]);

        $table->setNoResultsText(lang('export_no_configs'), lang('export_create_new'), $this->url('create'));

        $data = [];
        foreach ($configs as $config) {
            $settings = $config->getSettings();
            $data[] = [
                $config->name,
                lang('export_source_' . $config->source) ?: $config->source,
                strtoupper($settings['format'] ?? '—'),
                strtolower($settings['output'] ?? '—'),
                $config->getFormattedCreatedAt(),
                [
                    'toolbar_items' => [
                        'download' => [
                            'href'  => $this->url('run/' . $config->id),
                            'title' => lang('export_run'),
                        ],
                        'edit' => [
                            'href'  => $this->url('edit/' . $config->id),
                            'title' => lang('export_edit'),
                        ],
                        'remove' => [
                            'href'  => $this->url('delete/' . $config->id),
                            'title' => lang('export_delete'),
                        ],
                    ],
                ],
            ];
        }

        $table->setData($data);

        $table_url = ee('CP/URL')->make($this->base_url . '/index');

        $vars = [
            'table'      => $table->viewData($table_url),
            'create_url' => $this->url('create'),
        ];

        $this->setView('index', $vars);

        return $this;
    }
}
