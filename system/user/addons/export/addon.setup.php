<?php

use Mithra62\Export\Services\LoggerService;
use Mithra62\Export\Services\ActionsService;
use Mithra62\Export\Services\ParamsService;
use Mithra62\Export\Services\ExcelService;
use Mithra62\Export\Services\ExportService;
use Mithra62\Export\Services\EntryService;

return [
    'name'              => 'Export',
    'description'       => 'Export description',
    'version'           => '1.0.0',
    'author'            => 'mithra62',
    'author_url'        => 'fdsa',
    'namespace'         => 'Mithra62\Export',
    'settings_exist'    => false,
    'services' => [
        'ParamsService' => function ($addon) {
            return new ParamsService();
        },
        'LoggerService' => function ($addon) {
            return new LoggerService();
        },
        'ExcelService' => function ($addon) {
            return new ExcelService();
        },
        'ExportService' => function ($addon) {
            return new ExportService(ee('export:ParamsService'));
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
