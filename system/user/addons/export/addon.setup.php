<?php

use Mithra62\Export\Services\ActionsService;
use Mithra62\Export\Services\EntryService;
use Mithra62\Export\Services\ExcelService;
use Mithra62\Export\Services\ExportService;
use Mithra62\Export\Services\FormatsService;
use Mithra62\Export\Services\LoggerService;
use Mithra62\Export\Services\OutputService;
use Mithra62\Export\Services\ParamsService;
use Mithra62\Export\Services\SourcesService;

require_once __DIR__ . "/vendor/autoload.php";

return [
    'name' => 'Export',
    'description' => 'Export description',
    'version' => '1.0.0',
    'author' => 'mithra62',
    'author_url' => 'fdsa',
    'namespace' => 'Mithra62\Export',
    'settings_exist' => false,
    'services' => [
        'LoggerService' => function ($addon) {
            return new LoggerService();
        },
        'ExcelService' => function ($addon) {
            return new ExcelService();
        },
        'ExportService' => function ($addon) {
            $export = new ExportService();
            $export->setParams(ee('export:ParamsService'));
            $export->setSources(ee('export:SourcesService'));
            $export->setOutput(ee('export:OutputService'));
            $export->setFormats(ee('export:FormatsService'));
            return $export;
        },
        'ParamsService' => function ($addon) {
            return new ParamsService();
        },
        'SourcesService' => function ($addon) {
            return new SourcesService();
        },
        'OutputService' => function ($addon) {
            return new OutputService();
        },
        'FormatsService' => function ($addon) {
            return new FormatsService();
        },
    ],
    'services.singletons' => [
        'ActionsService' => function ($addon) {
            return new ActionsService();
        },
        'EntryService' => function ($addon) {
            return new EntryService();
        },
    ],
];
