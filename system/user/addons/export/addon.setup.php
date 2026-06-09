<?php

use Mithra62\Export\Fields\Date as FieldDate;
use Mithra62\Export\Fields\File as FieldFile;
use Mithra62\Export\Fields\FluidField;
use Mithra62\Export\Fields\Grid as FieldGrid;
use Mithra62\Export\Fields\Relationship;
use Mithra62\Export\Services\ActionsService;
use Mithra62\Export\Services\EntryService;
use Mithra62\Export\Services\ExcelService;
use Mithra62\Export\Services\ExportService;
use Mithra62\Export\Services\FieldsService;
use Mithra62\Export\Services\FormatsService;
use Mithra62\Export\Services\LoggerService;
use Mithra62\Export\Services\MemberService;
use Mithra62\Export\Services\OutputService;
use Mithra62\Export\Services\ParamsService;
use Mithra62\Export\Services\ModifiersService;
use Mithra62\Export\Services\SourcesService;
use Mithra62\Export\Services\XmlService;

const EXPORT_VERSION = '0.1.0';

require_once __DIR__ . "/vendor/autoload.php";

return [
    'name' => 'Export',
    'description' => 'Export description',
    'version' => EXPORT_VERSION,
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
            $export->setModifiers(ee('export:ModifiersService'));
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
        'XmlService' => function ($addon) {
            return new XmlService();
        },
        'ModifiersService' => function ($addon) {
            return new ModifiersService();
        },
    ],
    'services.singletons' => [
        'ActionsService' => function ($addon) {
            return new ActionsService();
        },
        'EntryService' => function ($addon) {
            return new EntryService();
        },
        'FieldsService' => function ($addon) {
            return new FieldsService();
        },
        'MemberService' => function ($addon) {
            return new MemberService();
        },
    ],
    'export' => [
        'fields' => [
            'date' => FieldDate::class,
            'file' => FieldFile::class,
            'relationship' => Relationship::class,
            'grid' => FieldGrid::class,
            'fluid_field' => FluidField::class,
        ],
    ],
];
