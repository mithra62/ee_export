<?php

use Mithra62\Export\Services\LoggerService;
use Mithra62\Export\Services\ActionsService;
use Mithra62\Export\Services\ParamsService;

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
    ],
    'services.singletons' => [
        'ActionsService' => function ($addon) {
            return new ActionsService();
        },
    ],
];
