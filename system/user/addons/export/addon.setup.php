<?php

use Mithra62\Export\Fields\Date as FieldDate;
use Mithra62\Export\Fields\File as FieldFile;
use Mithra62\Export\Fields\FluidField;
use Mithra62\Export\Fields\Grid as FieldGrid;
use Mithra62\Export\Fields\Relationship;
use Mithra62\Export\Formats\Csv;
use Mithra62\Export\Formats\Json;
use Mithra62\Export\Formats\Xlsx;
use Mithra62\Export\Formats\Xml;
use Mithra62\Export\Modifiers\EeDate;
use Mithra62\Export\Modifiers\EeDecrypt;
use Mithra62\Export\Modifiers\ReplaceWith;
use Mithra62\Export\Modifiers\UcFirst;
use Mithra62\Export\Modifiers\UcWords;
use Mithra62\Export\Output\Download;
use Mithra62\Export\Output\Local;
use Mithra62\Export\Services\ActionsService;
use Mithra62\Export\Services\CpService;
use Mithra62\Export\Services\EntryService;
use Mithra62\Export\Services\ExcelService;
use Mithra62\Export\Services\ExportService;
use Mithra62\Export\Services\FieldsService;
use Mithra62\Export\Services\FormatsService;
use Mithra62\Export\Services\LoggerService;
use Mithra62\Export\Services\MemberService;
use Mithra62\Export\Services\ModifiersService;
use Mithra62\Export\Services\OutputService;
use Mithra62\Export\Services\ParamsService;
use Mithra62\Export\Services\SourcesService;
use Mithra62\Export\Services\XmlService;
use Mithra62\Export\Sources\Entries;
use Mithra62\Export\Sources\Fluid as SourceFluid;
use Mithra62\Export\Sources\Grid as SourceGrid;
use Mithra62\Export\Sources\Members;
use Mithra62\Export\Sources\Sql;

const EXPORT_VERSION = '1.0.0-beta.2';

require_once __DIR__ . "/vendor/autoload.php";

return [
    'name' => 'Export',
    'description' => 'Export channel entries, members, Grid rows, Fluid instances, or SQL results to CSV, JSON, XLSX, or XML.',
    'version' => EXPORT_VERSION,
    'author' => 'mithra62',
    'author_url' => 'https://mithra62.com',
    'namespace' => 'Mithra62\Export',
    'settings_exist' => true,
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
        'CpService' => function ($addon) {
            return new CpService();
        },
    ],
    'models' => [
        'ExportConfiguration' => 'Models\ExportConfiguration',
    ],
    'tests' => [
        'path' => 'tests',
    ],
    'export' => [
        'sources' => [
            'entries' => Entries::class,
            'fluid' => SourceFluid::class,
            'grid' => SourceGrid::class,
            'members' => Members::class,
            'sql' => Sql::class,
        ],
        'formats' => [
            'csv' => Csv::class,
            'json' => Json::class,
            'xlsx' => Xlsx::class,
            'xml' => Xml::class,
        ],
        'modifiers' => [
            'ee_date' => EeDate::class,
            'ee_decrypt' => EeDecrypt::class,
            'replace_with' => ReplaceWith::class,
            'uc_first' => UcFirst::class,
            'uc_words' => UcWords::class,
        ],
        'outputs' => [
            'download' => Download::class,
            'local' => Local::class,
        ],
        'fields' => [
            'date' => FieldDate::class,
            'file' => FieldFile::class,
            'relationship' => Relationship::class,
            'grid' => FieldGrid::class,
            'fluid_field' => FluidField::class,
        ],
    ],
];
