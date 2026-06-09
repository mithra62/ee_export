# Extending Export

This document explains how to add custom Sources, Formats, Modifiers, Output destinations, and Field Handlers to the Export addon.

---

## Table of Contents

- [How the Plugin System Works](#how-the-plugin-system-works)
  - [Non-streaming path](#non-streaming-path)
  - [Streaming path](#streaming-path)
  - [Param namespacing](#param-namespacing)
  - [Class resolution](#class-resolution)
  - [Reading options inside a plugin](#reading-options-inside-a-plugin)
  - [Validation](#validation)
  - [The `fields=` exclusion param](#the-fields-exclusion-param)
- [1. Creating and Using Source Objects](#1-creating-and-using-source-objects)
  - [Contract](#contract)
  - [Inherited helpers](#inherited-helpers)
  - [Example — simple non-streaming source](#example--simple-non-streaming-orders-source)
  - [Streaming sources](#streaming-sources)
  - [Wiring a companion tag class](#wiring-a-companion-tag-class)
- [2. Creating and Using Format Objects](#2-creating-and-using-format-objects)
  - [Contract](#contract-1)
  - [Streaming interface](#streaming-interface)
  - [Inherited helpers](#inherited-helpers-1)
  - [Built-in formats](#built-in-formats)
  - [Example — streaming Tsv format](#example--streaming-tsv-tab-separated-format)
- [3. Creating and Using Modifier Objects](#3-creating-and-using-modifier-objects)
  - [Contract](#contract-2)
  - [Parameter system](#parameter-system)
  - [Chaining](#chaining)
  - [Built-in modifiers](#built-in-modifiers)
  - [Example — Truncate modifier](#example--truncate-modifier)
- [4. Creating and Using Output (Destination) Objects](#4-creating-and-using-output-destination-objects)
  - [Contract](#contract-3)
  - [Inherited helpers](#inherited-helpers-2)
  - [Built-in destinations](#built-in-destinations)
  - [Example — S3 destination](#example--s3-destination)
- [5. Creating Field Handlers](#5-creating-field-handlers)
  - [Contract](#contract-4)
  - [Registering a handler](#registering-a-handler)
  - [Built-in field handlers](#built-in-field-handlers)
  - [Minimal example](#minimal-example--rating-field-type)
  - [Example with context](#example-with-context--field-that-resolves-related-data)
  - [Detecting source context](#detecting-source-context)
- [6. Field Handler Context Reference](#6-field-handler-context-reference)
- [7. Registering Custom Plugins](#7-registering-custom-plugins)

---

## How the Plugin System Works

Every export runs through a five-stage pipeline. The pipeline has two execution paths depending on whether the active Source supports streaming.

### Non-streaming path

Used by Sources that load all data into memory before writing (e.g. `Members`, `Sql`).

```
Template tag params
    → AbstractTag.params()         collect and namespace params
    → ExportService.validate()     validate each plugin's options
    → ExportService.build()
        source.compile()           fetch all data; store in export_data[]
        modifiers.process()        transform fields across all rows
        format.compile(source)     write full file; return path
        output.process(path)       deliver file to browser / disk / etc.
```

### Streaming path

Used by Sources that declare `supportsStreaming(): bool { return true; }` (e.g. `Entries`, `Grid`). Keeps memory constant regardless of dataset size.

```
Template tag params
    → AbstractTag.params()
    → ExportService.validate()
    → ExportService.buildStreaming()
        source.openStream()        initialise cursor / state
        format.openFile()          open output file handle
        loop:
            source.nextChunk()     return up to chunk_size rows (empty = done)
            modifiers.process()    transform fields in chunk
            format.writeChunk()    append chunk to file
        source.closeStream()
        format.finalizeFile()      close file; return path
        output.process(path)       deliver file
```

### Param namespacing

Each layer of the pipeline reads only its own params. In a template tag, prefix params with the layer name:

```ee
{exp:export:members
    format="csv"                ← global
    source:limit="50"           ← read by Source
    format:separator=";"        ← read by Format
    output:filename="out.csv"   ← read by Destination
    modify:email="uc_first"     ← read by Modifiers
    output="download"           ← global
}
```

The prefix is stripped before options reach the plugin, so inside a `Format` class `getOption('separator')` returns `";"`.

### Class resolution

Each service converts the plain name to a class via `Str::studly()`:

| Param | Resolves to |
|---|---|
| `format="csv"` | `Mithra62\Export\Formats\Csv` |
| `output="local"` | `Mithra62\Export\Output\Local` |
| `source="members"` | `Mithra62\Export\Sources\Members` |
| `modify:field="uc_words"` | `Mithra62\Export\Modifiers\UcWords` |

Names with underscores work too: `my_format` → `MyFormat`.

### Reading options inside a plugin

```php
$this->getOption('key');            // returns null if not set
$this->getOption('key', 'default'); // returns default if not set
```

### Validation

Define a `$rules` array on your class. EE's validation engine runs it before `compile()` / `process()` is called.

```php
protected array $rules = [
    'filename' => 'required',
    'path'     => 'required|dirExists',
];
```

Add custom rules by overriding `getValidator()`:

```php
use ExpressionEngine\Service\Validation\Validator;

protected function getValidator(): Validator
{
    $validator = parent::getValidator();
    $validator->defineRule('dirExists', function ($key, $value) {
        return is_dir($value) ? true : 'directory does not exist';
    });
    return $validator;
}
```

### The `fields=` exclusion param

Every source calls `$this->cleanFields($row)` before appending each row to the output. That method reads the `fields` tag param as a **pipe-separated exclusion list** — any column name listed is removed from the row. All other columns pass through unchanged.

```ee
{exp:export:entries
    channel="blog"
    fields="field_internal_notes|field_raw_html"
    format="csv"
    output="download"
    output:filename="blog.csv"
}
```

`cleanFields()` does **not** filter to only the listed columns, and it does **not** reorder columns.

---

## 1. Creating and Using Source Objects

Sources fetch data and return it as a flat 2-D array (rows × columns) for the rest of the pipeline.

**Base class:** `Mithra62\Export\Plugins\AbstractSource`  
**Namespace:** `Mithra62\Export\Sources\`  
**Tag param:** `source="your_name"` (usually set by the companion tag class, not the template author)

### Contract

```php
public function compile(): AbstractSource
```

Either populate the export data and return `$this`, or throw `NoDataException` when there is nothing to export (the tag will produce no output rather than an error page).

### Inherited helpers

| Method | Purpose |
|---|---|
| `$this->getOption('key', $default)` | Read a source param |
| `$this->setExportData(array $rows)` | Store the 2-D result array |
| `$this->cleanFields(array $row)` | Remove columns listed in the `fields=` exclusion param |

### Example — simple non-streaming `Orders` source

```php
<?php
namespace Mithra62\Export\Sources;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Orders extends AbstractSource
{
    protected array $rules = [
        'source' => 'required',
    ];

    public function compile(): AbstractSource
    {
        $query = ee()->db
            ->select('order_id, customer_email, total, created_at')
            ->from('exp_orders');

        if ($this->getOption('status')) {
            $query->where('status', $this->getOption('status'));
        }

        if ($this->getOption('limit')) {
            $query->limit((int) $this->getOption('limit'));
        }

        $result = $query->get();

        if ($result->num_rows() === 0) {
            throw new NoDataException("No orders found");
        }

        $rows = [];
        foreach ($result->result_array() as $row) {
            $rows[] = $this->cleanFields($row);
        }

        $this->setExportData($rows);
        return $this;
    }
}
```

### Streaming sources

For large datasets, implement the streaming interface instead of loading all rows at once. Declare `supportsStreaming(): bool { return true; }` and implement `openStream()`, `nextChunk()`, and `closeStream()`. You still need a `compile()` method (it is called when the format does not support streaming, or as a fallback); the standard pattern is to drive the streaming methods from within it.

```php
<?php
namespace Mithra62\Export\Sources;

use CI_DB_result;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class BigOrders extends AbstractSource
{
    protected array $rules = ['source' => 'required'];

    protected int $stream_offset     = 0;
    protected int $stream_chunk_size = 500;

    // Tell the pipeline to use the streaming path
    public function supportsStreaming(): bool { return true; }

    // compile() is the non-streaming fallback; drive streaming methods from here
    public function compile(): AbstractSource
    {
        $this->openStream();
        $rows = [];
        while (true) {
            $chunk = $this->nextChunk();
            if (empty($chunk)) break;
            foreach ($chunk as $row) $rows[] = $row;
        }
        $this->closeStream();

        if (empty($rows)) throw new NoDataException("No orders found");

        $this->setExportData($rows);
        return $this;
    }

    public function openStream(): void
    {
        $this->stream_offset     = (int) $this->getOption('offset', 0);
        $this->stream_chunk_size = (int) $this->getOption('chunk_size', 500);
        // Any one-time setup (resolve IDs, load column definitions, etc.) goes here
    }

    public function nextChunk(): array
    {
        $result = ee()->db
            ->from('exp_orders')
            ->limit($this->stream_chunk_size, $this->stream_offset)
            ->get();

        if (!($result instanceof CI_DB_result) || $result->num_rows() === 0) {
            return []; // empty return signals end-of-stream
        }

        $rows = [];
        foreach ($result->result_array() as $row) {
            $rows[] = $this->cleanFields($row);
        }

        $this->stream_offset += count($rows);
        return $rows;
    }

    public function closeStream(): void
    {
        // Release any open cursors / handles if needed
    }
}
```

**Batch-loading within a chunk.** One of the main benefits of streaming is that you can batch-fetch supporting data (relationships, taxonomy, etc.) for all rows in the chunk with a single `WHERE id IN (...)` query instead of one query per row. Load the chunk's primary row set, collect the IDs you need, run the batch query, then merge before returning.

### Wiring a companion tag class

Built-in tags (`Members`, `Query`, etc.) hard-code their source name. For a custom source, add a tag class in `Tags/`:

```php
<?php
namespace Mithra62\Export\Tags;

class Orders extends AbstractTag
{
    public function process()
    {
        $params = $this->params();
        $params['source']          = 'orders';
        $params['source:status']   = $this->param('status');
        $params['source:limit']    = $this->param('limit');
        $params['source:offset']   = $this->param('offset', 0);
        $params['source:chunk_size'] = $this->param('chunk_size', 500);
        $this->compile($params);
    }
}
```

Template usage:

```ee
{exp:export:orders
    status="pending"
    fields="order_id|customer_email|total"
    format="csv"
    output="download"
    output:filename="orders.csv"
}
```

---

## 2. Creating and Using Format Objects

Formats receive compiled source data, write a file, and return its absolute path.

**Base class:** `Mithra62\Export\Plugins\AbstractFormat`  
**Namespace:** `Mithra62\Export\Formats\`  
**Tag param:** `format="your_name"`

### Contract

```php
public function compile(AbstractSource $source): string
```

Write the export to a file and return its absolute path. For non-streaming formats this is the only method required. For streaming formats, implement the three additional methods below.

### Streaming interface

All built-in formats support streaming. When a streaming source is paired with a streaming format, `ExportService` calls `openFile()` / `writeChunk()` / `finalizeFile()` directly and never calls `compile()`. Implement `compile()` anyway as a non-streaming fallback (call the streaming methods internally):

```php
public function supportsStreaming(): bool { return true; }

// Called once before any chunk arrives; $first_row is available for header writing
public function openFile(array $first_row = []): void {}

// Called once per chunk with the rows for that chunk
public function writeChunk(array $rows): void {}

// Called after the last chunk; close handles and return the file path
public function finalizeFile(): string { return ''; }
```

### Inherited helpers

| Method | Purpose |
|---|---|
| `$source->getExportData()` | 2-D array of rows keyed by column name (non-streaming path) |
| `$this->getCacheDirPath()` | Writable temp directory (trailing slash included) |
| `$this->getCacheFilename()` | Unique random filename with `.tmp` extension |
| `$this->writeContent($content, $path)` | Write a string to a file |
| `$this->getOption('key', $default)` | Read a `format:` param |

### Built-in formats

| Tag value | Class | Streaming | Required params | Notes |
|---|---|---|---|---|
| `csv` | `Csv` | ✅ | — | Optional: `separator` (`,`), `enclosure` (`"`), `escape` (`\`), `newline` (`\n`) |
| `json` | `Json` | ✅ | — | Writes a JSON array; nested arrays preserved natively |
| `xlsx` | `Xlsx` | ✅ | — | Optional: `bold_cols="y"` for bold header row (powered by OpenSpout) |
| `xml` | `Xml` | ✅ | `root_name`, `branch_name` | Both params are **required**; element names for root and each record |

> **Flat formats (CSV, XLSX):** Complex field values (arrays from Grid, Relationship, Fluid fields) are JSON-encoded into a single cell string via an internal `flattenValue()` helper.  
> **Native formats (JSON, XML):** Complex values are output as nested structures.

### Example — streaming `Tsv` (tab-separated) format

```php
<?php
namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;

class Tsv extends AbstractFormat
{
    protected string $path   = '';
    protected mixed  $fp     = null;
    protected bool   $header = false;

    public function supportsStreaming(): bool { return true; }

    // Non-streaming fallback — drives the streaming methods
    public function compile(AbstractSource $source): string
    {
        $rows = $source->getExportData();
        $this->openFile($rows[0] ?? []);
        $this->writeChunk($rows);
        return $this->finalizeFile();
    }

    public function openFile(array $first_row = []): void
    {
        $this->path   = $this->getCacheDirPath() . $this->getCacheFilename() . '.tsv';
        $this->fp     = fopen($this->path, 'w');
        $this->header = false;

        if (!empty($first_row)) {
            fputcsv($this->fp, array_keys($first_row), "\t");
            $this->header = true;
        }
    }

    public function writeChunk(array $rows): void
    {
        if (empty($rows)) return;

        if (!$this->header) {
            fputcsv($this->fp, array_keys(reset($rows)), "\t");
            $this->header = true;
        }

        foreach ($rows as $row) {
            fputcsv($this->fp, array_values($row), "\t");
        }
    }

    public function finalizeFile(): string
    {
        if ($this->fp) {
            fclose($this->fp);
            $this->fp = null;
        }
        return $this->path;
    }
}
```

Template usage:

```ee
{exp:export:members
    format="tsv"
    output="download"
    output:filename="members.tsv"
}
```

---

## 3. Creating and Using Modifier Objects

Modifiers transform individual field values. They run after the source produces each chunk and before the format writes it. Multiple modifiers can be chained per field using `|`.

**Base class:** `Mithra62\Export\Plugins\AbstractModifier`  
**Namespace:** `Mithra62\Export\Modifiers\`  
**Tag param:** `modify:field_name="modifier_name[param1][param2]"`

### Contract

```php
public function process(mixed $value): mixed
```

Receive a value, return the transformed value. The type may change (e.g. int → string is fine).

### Parameter system

Declare parameter names positionally in `$params`. Template values are passed in bracket syntax and mapped to names by position:

```php
protected array $params = ['search', 'replace'];
// modify:field="str_replace[old][new]"
// → getParam('search') returns 'old'
// → getParam('replace') returns 'new'
```

Access them with:

```php
$this->getParam('search');            // returns '' if not set
$this->getParam('search', 'default'); // returns 'default' if not set
```

### Chaining

```ee
modify:email="ee_decrypt|uc_first"
```

Modifiers run left to right. The output of each becomes the input of the next.

### Built-in modifiers

| Tag syntax | Class | Params | Effect |
|---|---|---|---|
| `ee_date[format]` | `EeDate` | `format` | Format Unix timestamp via `ee()->localize->format_date()` |
| `ee_decrypt` | `EeDecrypt` | — | Decrypt via `ee('Encrypt')->decrypt()` |
| `replace_with[value]` | `ReplaceWith` | `with` | Replace the entire value with a literal string (default replacement: `N/A`) |
| `uc_first` | `UcFirst` | — | `ucfirst()` on the value |
| `uc_words` | `UcWords` | — | `ucwords()` on the value |

### Example — `Truncate` modifier

```php
<?php
namespace Mithra62\Export\Modifiers;

use Mithra62\Export\Plugins\AbstractModifier;

class Truncate extends AbstractModifier
{
    protected array $params = ['length', 'suffix'];

    public function process(mixed $value): mixed
    {
        $length = (int) $this->getParam('length', 100);
        $suffix = $this->getParam('suffix', '...');

        if (strlen((string) $value) <= $length) {
            return $value;
        }

        return substr((string) $value, 0, $length) . $suffix;
    }
}
```

Template usage:

```ee
{exp:export:members
    modify:bio="truncate[120][…]"
    modify:username="uc_first"
    modify:join_date="ee_date[%Y-%m-%d]"
    format="csv"
    output="download"
    output:filename="members.csv"
}
```

---

## 4. Creating and Using Output (Destination) Objects

Destinations receive the path to the generated export file and deliver it — to the browser, a directory, a remote service, etc.

**Base class:** `Mithra62\Export\Plugins\AbstractDestination`  
**Namespace:** `Mithra62\Export\Output\`  
**Tag param:** `output="your_name"`

### Contract

```php
public function process(string $finished_export): bool|int
```

`$finished_export` is the absolute path to the finished export file. Return truthy on success.

### Inherited helpers

| Item | Purpose |
|---|---|
| `$this->getOption('key', $default)` | Read an `output:` param |
| `protected bool $force_exit = false` | Set to `true` to call `exit` after delivery (required for browser downloads) |
| `$this->shouldDie()` | Read by `ExportService`; triggers exit if `true` |

### Built-in destinations

| Tag value | Class | Required params | Behaviour |
|---|---|---|---|
| `download` | `Download` | `filename` | Streams file to browser via `readfile()`; sets `force_exit = true`; exits after delivery |
| `local` | `Local` | `filename`, `path` | Copies file to a local directory; validates directory exists and is writable |

### Example — `S3` destination

```php
<?php
namespace Mithra62\Export\Output;

use ExpressionEngine\Service\Validation\Validator;
use Mithra62\Export\Plugins\AbstractDestination;

class S3 extends AbstractDestination
{
    protected array $rules = [
        'bucket'   => 'required',
        'filename' => 'required',
    ];

    public function process(string $finished_export): bool|int
    {
        $bucket   = $this->getOption('bucket');
        $filename = $this->getOption('filename');
        $prefix   = rtrim($this->getOption('prefix', ''), '/') . '/';

        $s3 = new \YourS3Client();
        return $s3->upload($bucket, $prefix . $filename, fopen($finished_export, 'rb'));
    }

    protected function getValidator(): Validator
    {
        $validator = parent::getValidator();
        $validator->defineRule('validBucket', function ($key, $value) {
            return preg_match('/^[a-z0-9\-\.]+$/', $value) ? true : 'invalid bucket name';
        });
        return $validator;
    }
}
```

Template usage:

```ee
{exp:export:members
    format="csv"
    output="s3"
    output:bucket="my-exports-bucket"
    output:prefix="members/"
    output:filename="members-export.csv"
}
```

---

## 5. Creating Field Handlers

Field handlers process individual custom field values — date formatting, file URL resolution, relationship lookups, etc. The same handler is invoked for a given field type regardless of which source (Entries, Grid, Members) produces the row, making it straightforward for third-party addons to teach Export how to handle their own field types.

**Base class:** `Mithra62\Export\Plugins\AbstractField`  
**Namespace:** convention — place handlers in a `Fields/` directory within your addon  
**Discovered via:** `addon.setup.php` `export.fields` declaration (see below)

### Contract

```php
abstract public function process(
    mixed $raw_value,
    array $field_info,
    int   $entry_id,
    array $context = []
): mixed;
```

| Argument | Type | Description |
|---|---|---|
| `$raw_value` | `mixed` | Raw value from the storage column (e.g. `channel_data.field_id_X`) |
| `$field_info` | `array` | Field definition: `field_id`, `field_name`, `field_type`, `field_label`, `field_settings` (decoded array) |
| `$entry_id` | `int` | The entry ID — or `row_id` in Grid context, or `member_id` in Members context (see context table below) |
| `$context` | `array` | Pre-fetched batch data passed by the source (see §6) |

**Return type convention:**
- Return a **scalar** for simple values (string, int, float)
- Return an **array** for complex values (relationships, grid rows, fluid instances)
- Flat formats (CSV, XLSX) JSON-encode arrays into a single cell via their internal `flattenValue()` helper
- Native formats (JSON, XML) output arrays as nested structures

### Registering a handler

Any installed EE addon can register field handlers in its own `addon.setup.php` under the `export.fields` key. No changes to Export's source code are required.

```php
// system/user/addons/my_addon/addon.setup.php
return [
    'name'      => 'My Addon',
    'namespace' => 'MyAddon',
    // ...
    'export' => [
        'fields' => [
            'bloqs'        => \MyAddon\Export\Fields\Bloqs::class,
            'my_fieldtype' => \MyAddon\Export\Fields\MyFieldtype::class,
        ],
    ],
];
```

`FieldsService` scans all installed addons via `ee('App')->getProviders()` on first use and merges all `export.fields` maps. Export's own built-in handlers form the baseline; third-party declarations are merged after and can override built-ins if needed.

### Built-in field handlers

| Field type | Handler class | Behaviour |
|---|---|---|
| `date` | `Fields\Date` | Casts the raw Unix timestamp to `int` |
| `file` | `Fields\File` | Converts `{filedir_N}filename` token to an absolute URL |
| `relationship` | `Fields\Relationship` | Returns `[['entry_id' => X, 'title' => Y], ...]` using pre-fetched `rel_cache` |
| `grid` | `Fields\Grid` | Returns an array of mapped row objects using pre-fetched `grid_data` |
| `fluid_field` | `Fields\FluidField` | Returns a typed instance array using pre-fetched fluid data |

Field types with no registered handler (e.g. `text`, `textarea`, `select`) pass through as raw string values automatically.

### Minimal example — `Rating` field type

```php
<?php
namespace MyAddon\Export\Fields;

use Mithra62\Export\Plugins\AbstractField;

class Rating extends AbstractField
{
    public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
    {
        // Stored as an integer string "1"–"5"; cast and default to 0
        return $raw_value !== null ? (int) $raw_value : 0;
    }
}
```

Register in `addon.setup.php`:

```php
'export' => [
    'fields' => [
        'rating' => \MyAddon\Export\Fields\Rating::class,
    ],
],
```

### Example with context — field that resolves related data

```php
<?php
namespace MyAddon\Export\Fields;

use Mithra62\Export\Plugins\AbstractField;

class ProductLink extends AbstractField
{
    public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
    {
        if (empty($raw_value)) {
            return [];
        }

        // raw_value stores pipe-separated product IDs
        $product_ids = array_filter(explode('|', $raw_value));

        $result = ee()->db
            ->select('product_id, product_name, sku')
            ->from('exp_my_products')
            ->where_in('product_id', $product_ids)
            ->get();

        $items = [];
        foreach ($result->result_array() as $row) {
            $items[] = [
                'product_id'   => $row['product_id'],
                'product_name' => $row['product_name'],
                'sku'          => $row['sku'],
            ];
        }

        return $items;
    }
}
```

### Detecting source context

Handlers can branch based on which source invoked them using `$context['source_type']`. This is important when a handler would otherwise use `$field_info['field_id']` or the `$entry_id` argument to make a DB query — those values mean different things depending on context.

```php
public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
{
    $source = $context['source_type'] ?? 'entries';

    if ($source === 'grid') {
        // $entry_id here is the grid row_id, NOT the channel entry_id
        // $field_info['field_id'] is the col_id, NOT a channel_fields.field_id
        $real_entry_id = $context['entry_id']; // actual channel_titles.entry_id
        $row_id        = $context['row_id'];   // actual channel_grid_field_X.row_id
        $col_id        = $context['col_id'];   // actual grid_columns.col_id

    } elseif ($source === 'member') {
        // $entry_id is the member_id
        $member_id = $context['member_id']; // same value, explicit key

    } else {
        // Entries source (source_type absent means entries)
        // $entry_id is the genuine channel_titles.entry_id
        // $field_info['field_id'] is the genuine channel_fields.field_id
    }

    return $raw_value ?? '';
}
```

---

## 6. Field Handler Context Reference

The `$context` array passed to `AbstractField::process()` varies by source. ✅ = present and populated; — = not present (treat as empty).

| Context key | Type | Entries | Grid | Members | Description |
|---|---|---|---|---|---|
| `source_type` | `string` | — | `'grid'` | `'member'` | Source identifier; absent means Entries |
| `rel_data` | `array` | ✅ | ✅ | — | `[entry_id\|row_id][field_id\|col_id][] = child_entry_id` |
| `rel_cache` | `array` | ✅ | ✅ | — | `[child_entry_id] = ['title' => ..., ...]` |
| `grid_data` | `array` | ✅ | — | — | `[field_id][entry_id][] = raw grid row array` |
| `channel_fields` | `array` | ✅ | — | — | `[field_id] = field_info array` for the whole channel |
| `grid_columns` | `array` | ✅ | — | — | `[field_id][col_id] = col_info array` |
| `fluid_instances` | `array` | ✅ | — | — | `[entry_id][fluid_field_id][] = fluid_field_data row` |
| `fluid_values` | `array` | ✅ | — | — | `[sub_field_id][field_data_id] = scalar value` |
| `fluid_grid_data` | `array` | ✅ | — | — | `[sub_field_id][fluid_instance_id][] = grid row` |
| `entry_id` | `int` | — | ✅ | — | Actual `channel_titles.entry_id` (use when `$entry_id` arg = row_id in Grid context) |
| `row_id` | `int` | — | ✅ | — | Actual `channel_grid_field_X.row_id` |
| `col_id` | `int` | — | ✅ | — | Actual `grid_columns.col_id` |
| `member_id` | `int` | — | — | ✅ | Actual `exp_members.member_id` (same as `$entry_id` arg in Members context) |

### `rel_data` key detail

The shape is identical between Entries and Grid — only the outer key scope differs:

```
Entries:  $context['rel_data'][$entry_id][$field_id][] = $child_entry_id
Grid:     $context['rel_data'][$row_id][$col_id][]     = $child_entry_id
```

`Fields\Relationship` uses the `$entry_id` argument as the outer key in both cases — which resolves correctly in Grid because the source passes `$row_id` as that argument.

---

## 7. Registering Custom Plugins

No registration is required for Sources, Formats, Modifiers, and Outputs. The factory services resolve classes purely by name and namespace. Place your class in the correct namespace and ensure it is autoloaded — the pipeline will find it automatically.

Field Handlers are the exception: they must be declared in an `addon.setup.php` `export.fields` map (see §5) because they are keyed by field type string rather than resolved by a user-supplied name.

### Adding classes to the Export addon directly

Drop your file into the matching subdirectory (`Sources/`, `Formats/`, `Modifiers/`, `Output/`, `Fields/`) under `system/user/addons/export/`. Composer's PSR-4 autoloader for the `Mithra62\Export\` namespace picks it up automatically.

### Distributing in a separate addon

Keep your classes in your own addon's namespace. For Sources, Formats, Modifiers, and Outputs, the factory services use `Str::studly()` on the tag param to build a class name — they search the `Mithra62\Export\` namespace. To make your class discoverable under that namespace from an external addon, either:

- Register a PSR-4 autoload alias mapping `Mithra62\Export\Sources\YourSource` → your file, **or**
- Add your class directly to this addon's `Sources/` directory

For **Field Handlers**, use the `addon.setup.php` `export.fields` declaration pattern described in §5 — no namespace aliasing needed.

### Custom tag method routing

EE routes `{exp:export:foo}` to a tag class at `Tags\Foo`. Add your tag class to `Tags/` following the same pattern as the built-in ones (`Tags\Entries`, `Tags\Members`, etc.) and EE routes it automatically.
