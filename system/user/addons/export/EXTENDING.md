# Extending Export

Export's plugin system lets any installed ExpressionEngine add-on register custom Sources, Formats, Modifiers, Output destinations, and Field Handlers. No changes to Export's own source code. No hooks. Just a key in `addon.setup.php` and a class that follows the contract.

> **Extension requires a standalone EE add-on.** You cannot extend Export by editing its files directly. All custom plugin code, companion template tags, and `addon.setup.php` declarations must live in your own separately-installed EE add-on.

---

## Prerequisites

Extension means writing a separate EE add-on, not dropping a file into Export's directory. A real addon with its own namespace, its own `addon.setup.php`, and its own autoloading. The checklist below is the minimum bar to get started.

- ExpressionEngine with Export installed and enabled (see [System Requirements](README.md#system-requirements))
- Basic EE add-on development: `addon.setup.php`, PSR-4 autoloading, PHP 8.0+
- Your add-on's Composer/autoloading configured for its namespace

---

## Table of Contents

- [§1 Quickstart: Your First Extension Add-on](#1-quickstart-your-first-extension-add-on)
- [§2 Registering Custom Plugins](#2-registering-custom-plugins)
- [§3 How the Plugin System Works](#3-how-the-plugin-system-works)
  - [Non-streaming path](#non-streaming-path)
  - [Streaming path](#streaming-path)
  - [Param namespacing](#param-namespacing)
  - [Class resolution](#class-resolution)
  - [Reading options inside a plugin](#reading-options-inside-a-plugin)
  - [Validation](#validation)
  - [Column selection — `fields` and `exclude`](#column-selection--fields-and-exclude)
- [§4 Creating Sources](#4-creating-sources)
  - [Contract](#contract)
  - [Inherited helpers](#inherited-helpers)
  - [Example — simple non-streaming source](#example--simple-non-streaming-orders-source)
  - [Streaming sources](#streaming-sources)
  - [Wiring a companion tag class](#wiring-a-companion-tag-class)
  - [CP Form Fields](#cp-form-fields)
- [§5 Creating Formats](#5-creating-formats)
  - [Contract](#contract-1)
  - [Streaming interface](#streaming-interface)
  - [Inherited helpers](#inherited-helpers-1)
  - [Built-in formats](#built-in-formats)
  - [Example — streaming Tsv format](#example--streaming-tsv-tab-separated-format)
  - [CP Form Fields](#cp-form-fields-1)
- [§6 Creating Modifiers](#6-creating-modifiers)
  - [Contract](#contract-2)
  - [Parameter system](#parameter-system)
  - [Chaining](#chaining)
  - [Built-in modifiers](#built-in-modifiers)
  - [Example — Truncate modifier](#example--truncate-modifier)
- [§7 Creating Output Destinations](#7-creating-output-destinations)
  - [Contract](#contract-3)
  - [Inherited helpers](#inherited-helpers-2)
  - [Built-in destinations](#built-in-destinations)
  - [Example — S3 destination](#example--s3-destination)
  - [CP Form Fields](#cp-form-fields-2)
- [§8 Creating Field Handlers](#8-creating-field-handlers)
  - [Contract](#contract-4)
  - [Registering a handler](#registering-a-handler)
  - [Built-in field handlers](#built-in-field-handlers)
  - [Minimal example](#minimal-example--rating-field-type)
  - [Example with context](#example-with-context--field-that-resolves-related-data)
  - [Detecting source context](#detecting-source-context)
- [§9 Field Handler Context Reference](#9-field-handler-context-reference)
- [§10 Using Third-Party Plugins with Built-In Tags](#10-using-third-party-plugins-with-built-in-tags)

---

## §1 Quickstart: Your First Extension Add-on

Everything in this document references the same fictional `store_export` add-on. Walk through this section once and the examples will be self-evident; skim it and you'll spend the rest of the document interpolating.

### What we'll build

An add-on named `store_export` (PHP namespace `Acme\StoreExport`) that:
- Registers an `orders` source that reads from a `exp_store_orders` table
- Exposes it as the template tag `{exp:store_export:orders}`

### Directory layout

```
system/user/addons/store_export/
  addon.setup.php
  Sources/
    Orders.php
  Tags/
    Orders.php
```

### `addon.setup.php`

```php
<?php

use Acme\StoreExport\Sources\Orders as OrdersSource;

return [
    'author'         => 'Acme',
    'author_url'     => 'https://acme.example',
    'name'           => 'Store Export',
    'description'    => 'Adds an Orders source to the Export addon.',
    'version'        => '1.0.0',
    'namespace'      => 'Acme\\StoreExport',
    'settings_exist' => 'n',
    'has_tags'       => 'y',

    // Tell Export about our custom plugins
    'export' => [
        'sources' => [
            'orders' => OrdersSource::class,
        ],
    ],
];
```

Only the layers you extend need to appear in the `export` key. Omit the others entirely.

### `Sources/Orders.php`

```php
<?php

namespace Acme\StoreExport\Sources;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Orders extends AbstractSource
{
    protected array $rules = [
        'source' => 'required',
    ];

    public function compile(): static
    {
        $status = $this->getOption('status', 'complete');

        $query = ee()->db
            ->select('order_id, customer_email, total, status, order_date')
            ->from(ee()->db->dbprefix . 'store_orders')
            ->where('status', $status);

        if ($limit = (int) $this->getOption('limit')) {
            $query->limit($limit, (int) $this->getOption('offset', 0));
        }

        $result = $query->get();

        if ($result->num_rows() === 0) {
            throw new NoDataException('No orders found.');
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

### `Tags/Orders.php`

Custom sources need a companion tag class because each built-in Export tag hardcodes its own source key. The companion tag sets `source = 'orders'` in PHP and delegates to Export's pipeline via `$this->compile()`.

```php
<?php

namespace Acme\StoreExport\Tags;

use Mithra62\Export\Tags\AbstractTag;

class Orders extends AbstractTag
{
    public function process(): void
    {
        $params = $this->params();

        $params['source']              = 'orders';
        $params['source:status']       = $this->param('status', 'complete');
        $params['source:limit']        = $this->param('limit');
        $params['source:offset']       = $this->param('offset', 0);
        $params['source:chunk_size']   = $this->param('chunk_size', 500);

        $this->compile($params);
    }
}
```

> **`AbstractTag` is in Export.** Your companion tag extends `Mithra62\Export\Tags\AbstractTag` — this gives you `$this->compile()`, `$this->param()`, `$this->params()`, and the `{if no_results}` mechanism automatically.

### Template usage

```ee
{exp:store_export:orders
    status="complete"
    format="csv"
    output="download"
    filename="orders.csv"
    {if no_results}No orders to export.{/if}
}
```

---

## §2 Registering Custom Plugins

All five extension layers use the same `addon.setup.php` declaration. Declare your classes under the `export` key and Export discovers them automatically — no changes to Export's source code are needed.

### Full `addon.setup.php` `export` key

```php
// system/user/addons/store_export/addon.setup.php
return [
    'name'      => 'Store Export',
    'namespace' => 'Acme\\StoreExport',
    // ...
    'export' => [
        'sources' => [
            'orders' => \Acme\StoreExport\Sources\Orders::class,
        ],
        'formats' => [
            'tsv' => \Acme\StoreExport\Formats\Tsv::class,
        ],
        'outputs' => [
            's3' => \Acme\StoreExport\Outputs\S3::class,
        ],
        'modifiers' => [
            'truncate'   => \Acme\StoreExport\Modifiers\Truncate::class,
            'strip_tags' => \Acme\StoreExport\Modifiers\StripTags::class,
        ],
        'fields' => [
            'rating'       => \Acme\StoreExport\Fields\Rating::class,
            'product_link' => \Acme\StoreExport\Fields\ProductLink::class,
        ],
    ],
];
```

Any subset of layers may be declared — omit keys for layers your add-on does not extend.

### How discovery works

The discovery mechanism is worth understanding because it determines load order and what "override" actually means.

`AbstractService::getProviderMap(string $layer)` scans all installed add-ons via `ee('App')->getProviders()`, collects every `export.$layer` map, and merges them. Export's own built-in declarations form the baseline; third-party declarations are merged afterward and can override built-ins when needed. The result is cached statically so the scan runs at most once per PHP request per layer.

### Override precedence

```
addon.setup.php declaration (third-party) → highest priority
addon.setup.php declaration (Export built-in)
Str::studly() namespace fallback          → lowest priority
```

A third-party add-on can replace a built-in source, format, output, modifier, or field handler entirely by declaring the same key with a different class. This also replaces its CP presence entirely — there's no per-field merge between the overridden class's `getCpFields()` and the original's; whichever class wins the key resolution is the one whose fields render.

### Namespace fallback

For Sources, Formats, Outputs, and Modifiers, classes placed directly in the `Mithra62\Export\` namespace subtrees are still discovered automatically via `Str::studly()` if no `addon.setup.php` entry exists for that key. The declaration approach is preferred for distributed add-ons.

### Companion tag routing

EE routes `{exp:your_addon:foo}` to `Tags\Foo::process()` in your add-on's namespace. Add a tag class to `Tags/` extending `Mithra62\Export\Tags\AbstractTag`, set up params in `process()`, and call `$this->compile($params)`. No additional registration is required; EE discovers the tag class automatically.

**Custom sources always need a companion tag.** The built-in Export tags (`{exp:export:entries}`, `{exp:export:members}`, etc.) hardcode their own source key in PHP. There is no template-level `source=` override. To expose a custom source, your add-on must provide its own tag class that sets `$params['source'] = 'your_source_key'` before calling `$this->compile()`.

**Custom formats, outputs, and modifiers do not need a companion tag.** Once registered, they can be used by name as param values in any first-party or third-party tag. See [§10](#10-using-third-party-plugins-with-built-in-tags) for examples.

---

## §3 How the Plugin System Works

Every export runs through a five-stage pipeline. The pipeline has two execution paths depending on whether the active Source supports streaming.

### Non-streaming path

Used by Sources that load all data into memory before writing.

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

Used by Sources that declare `supportsStreaming(): bool { return true; }`. Keeps memory constant regardless of dataset size.

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

Each layer reads only its own params. In a template tag, prefix params with the layer name:

```ee
{exp:store_export:orders
    format="csv"               ← global
    source:limit="50"          ← read by Source
    format:separator=";"       ← read by Format
    output:filename="out.csv"  ← read by Destination
    modify:email="uc_first"    ← read by Modifiers
    output="download"          ← global
}
```

The prefix is stripped before options reach the plugin, so inside a Format class `getOption('separator')` returns `";"`.

### Class resolution

Each service converts the plain name to a class via `Str::studly()`:

| Param | Resolves to (built-in) |
|---|---|
| `format="csv"` | `Mithra62\Export\Formats\Csv` |
| `output="local"` | `Mithra62\Export\Output\Local` |
| `modify:field="uc_words"` | `Mithra62\Export\Modifiers\UcWords` |

When a key is declared in `addon.setup.php`, the declared class is used instead. Names with underscores work: `my_format` → `MyFormat`.

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

> **CP inline validation.** Rules declared in `$rules` and custom rules registered via `$validator->defineRule()` inside `getValidator()` are automatically surfaced in the Export Control Panel Create/Edit form as inline fieldset errors — no extra wiring needed. `Services/CpValidationBridge` instantiates the active source, format, and output drivers on every form POST, calls `validate()` on each, and maps driver param names back to CP POST field names (e.g. `channel` → `src_entries_channel`, `path` → `output_path`).

### Column selection — `fields` and `exclude`

Every source calls `$this->cleanFields($row)` before appending each row to the output. Two params control column filtering:

| Scenario | Behaviour |
|---|---|
| `fields` present | Return **only** the listed columns, in the order declared. `exclude` is ignored. |
| `exclude` present, `fields` absent | Remove the listed columns; return everything else. |
| Neither present | Return the full row unchanged. |

Both params accept pipe-separated column names and work identically across all sources.

---

## §4 Creating Sources

Sources fetch data and return it as a flat 2-D array (rows × columns) for the rest of the pipeline.

**Base class:** `Mithra62\Export\Plugins\AbstractSource`  
**Tag param:** `source="your_key"` (set by your companion tag class — not by the template author directly)

### Contract

```php
public function compile(): static
```

Either populate the export data and return `$this`, or throw `NoDataException` when there is nothing to export (the tag produces no output rather than an error page).

### Inherited helpers

| Method | Purpose |
|---|---|
| `$this->getOption('key', $default)` | Read a source param |
| `$this->setExportData(array $rows)` | Store the 2-D result array |
| `$this->cleanFields(array $row)` | Filter columns via `fields` whitelist or `exclude` blacklist |

### Example — simple non-streaming `Orders` source

```php
<?php

namespace Acme\StoreExport\Sources;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Orders extends AbstractSource
{
    protected array $rules = [
        'source' => 'required',
    ];

    public function compile(): static
    {
        $query = ee()->db
            ->select('order_id, customer_email, total, created_at')
            ->from(ee()->db->dbprefix . 'store_orders');

        if ($status = $this->getOption('status')) {
            $query->where('status', $status);
        }

        if ($limit = (int) $this->getOption('limit')) {
            $query->limit($limit);
        }

        $result = $query->get();

        if ($result->num_rows() === 0) {
            throw new NoDataException('No orders found.');
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

For large datasets, implement the streaming interface instead of loading all rows at once. Declare `supportsStreaming(): bool { return true; }` and implement `openStream()`, `nextChunk()`, and `closeStream()`. A `compile()` method is still required as a non-streaming fallback; the standard pattern is to drive the streaming methods from within it.

```php
<?php

namespace Acme\StoreExport\Sources;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class OrdersStream extends AbstractSource
{
    protected array $rules = ['source' => 'required'];

    protected int $stream_offset     = 0;
    protected int $stream_chunk_size = 500;

    public function supportsStreaming(): bool { return true; }

    // Non-streaming fallback — drives the streaming methods
    public function compile(): static
    {
        $this->openStream();
        $rows = [];
        while (true) {
            $chunk = $this->nextChunk();
            if (empty($chunk)) break;
            foreach ($chunk as $row) $rows[] = $row;
        }
        $this->closeStream();

        if (empty($rows)) throw new NoDataException('No orders found.');

        $this->setExportData($rows);
        return $this;
    }

    public function openStream(): void
    {
        $this->stream_offset     = (int) $this->getOption('offset', 0);
        $this->stream_chunk_size = (int) $this->getOption('chunk_size', 500);
        // One-time setup: resolve IDs, load column definitions, etc.
    }

    public function nextChunk(): array
    {
        $result = ee()->db
            ->from(ee()->db->dbprefix . 'store_orders')
            ->limit($this->stream_chunk_size, $this->stream_offset)
            ->get();

        if ($result->num_rows() === 0) {
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

**Batch-loading within a chunk.** One main benefit of streaming is that you can batch-fetch supporting data (relationships, taxonomy, etc.) for all rows in a chunk with a single `WHERE id IN (...)` query instead of one query per row. Load the chunk's primary rows, collect the IDs you need, run the batch query, then merge before returning.

### Wiring a companion tag class

Custom sources must be paired with a companion tag class in your add-on's `Tags/` directory. The companion tag sets the source key in PHP before calling Export's pipeline via `$this->compile()`.

```php
<?php

namespace Acme\StoreExport\Tags;

use Mithra62\Export\Tags\AbstractTag;

class Orders extends AbstractTag
{
    public function process(): void
    {
        $params = $this->params();

        $params['source']            = 'orders'; // must match key in addon.setup.php
        $params['source:status']     = $this->param('status', 'complete');
        $params['source:limit']      = $this->param('limit');
        $params['source:offset']     = $this->param('offset', 0);
        $params['source:chunk_size'] = $this->param('chunk_size', 500);

        $this->compile($params);
    }
}
```

Template usage:

```ee
{exp:store_export:orders
    status="complete"
    exclude="order_id|customer_email"
    format="csv"
    output="download"
    filename="orders.csv"
    {if no_results}No orders to export.{/if}
}
```

### CP Form Fields

Registering a source via `addon.setup.php` makes it usable from a template tag. It does not, by itself, put anything in the Export Control Panel's Create/Edit form — that form builds its Source dropdown and per-source fieldsets from the same provider map, but it needs to know what fields your source actually takes. You tell it by overriding `getCpFields()`:

```php
public function getCpFields(array $context = []): array
{
    return [
        [
            'name'  => 'status',
            'type'  => 'select',
            'label' => 'orders_field_status',
            'choices' => ['pending' => 'Pending', 'complete' => 'Complete'],
            'default' => 'complete',
        ],
        ['name' => 'limit', 'type' => 'text', 'label' => 'orders_field_limit'],
    ];
}
```

This is optional. A source with no `getCpFields()` override (the default on `AbstractPlugin` returns `[]`) still works fine via its companion tag — it just won't appear configurable in the CP, which is a reasonable choice for a source that's only ever used directly from templates.

**Descriptor shape.** Each array in the returned list describes one field:

| Key | Required | Meaning |
|---|---|---|
| `name` | yes | Bare param name, no prefix — what `getOption()` would read |
| `type` | yes | `text`, `textarea`, `select`, `checkbox`, `radio`, `toggle`, or `html` |
| `label` | yes | Lang key for the field's displayed title |
| `desc` | no | Lang key for a description line under the title |
| `required` | no | `true` calls the field's `setRequired(true)` |
| `default` | no | Fallback value when nothing is stored yet |
| `choices` | no | Static `[value => label]` array for `select`/`checkbox`/`radio` |
| `choices_callback` | no | `callable(array $context): array` — use this instead of `choices` when the list depends on a lookup, like a channel list |
| `value_callback` | no | `callable(array $context): mixed` — overrides the plain settings lookup, e.g. for date normalization |
| `content` / `content_callback` | `html` type only | Static or dynamic raw HTML string |
| `maxlength`, `placeholder` | no | Passed straight through to the field |
| `group` | no | Override the auto-assigned fieldset group, if you need a different show/hide scope than your own |
| `scoped` | no | `true` stores this field under `{your_key}:{name}` instead of `source:{name}` — see below |

`$context` is passed to every callback and always contains `settings` (the stored settings for this source, already stripped to bare names), `cp` (the `CpService` instance, useful for lookups like `getChannelList()`), `source_key` (your registered key), and `field_name` (the field's actual rendered `name=""` attribute, set just before the callback runs — handy for `html`-type fields that need to reference their own name).

**Why `scoped` exists.** Two sources both rendering a field called `channel` would otherwise collide: switching the Source dropdown in the editor from one to the other would pre-fill the second source's channel field with the first one's stored value, because both are stored at the same key (`source:channel`). Setting `'scoped' => true` stores the value at `{your_key}:channel` instead, so each source keeps its own independent value no matter how many times someone flips the Source dropdown while editing. Export's own Grid and Fluid sources both use this for their `channel` and `field` descriptors — copy that pattern if your source has a field whose name might collide with another source's field of the same name.

**What you cannot express this way.** A field whose choices depend on another field's *live* value, the way Grid and Fluid's field selector depends on which channel was just chosen, needs actual JavaScript — `choices_callback` only runs once, at page render. For that case, use the same AJAX endpoint Grid and Fluid already use, via the exposed global helper:

```js
jQuery(function ($) {
    window.Export.wireChannelToField(
        '[name="src_orders_warehouse_id"]', // your channel-like select
        '[name="src_orders_bin_id"]',       // the select it should repopulate
        'your_field_type'                   // passed through to the AJAX endpoint
    );
});
```

`Export.wireChannelToField()` is defined in Export's own `javascript/export.js` and posts to the same `action=fields` endpoint Export's CP already exposes (`Ajax.php`) — it isn't source-key aware, it just needs a channel ID and a field type, so it works for any source's cascading select pair. Load your own JS file via `ee()->cp->load_package_js('your_addon')` from wherever your add-on builds its own CP pages, if it has any; Export's Create/Edit form loads `export.js` (and therefore this helper) on its own pages already.

---

## §5 Creating Formats

Formats receive compiled source data, write a file, and return its absolute path.

**Base class:** `Mithra62\Export\Plugins\AbstractFormat`  
**Tag param:** `format="your_key"`

Once registered in your `addon.setup.php`, a custom format can be used in any Export template tag — including the built-in first-party tags. See [§10](#10-using-third-party-plugins-with-built-in-tags).

### Contract

```php
public function compile(AbstractSource $source): string
```

Write the export to a file and return its absolute path. For non-streaming formats this is the only method required.

### Streaming interface

When a streaming source is paired with a streaming format, `ExportService` calls `openFile()` / `writeChunk()` / `finalizeFile()` directly and never calls `compile()`. Implement `compile()` anyway as a non-streaming fallback (call the streaming methods internally):

```php
public function supportsStreaming(): bool { return true; }

// Called once before any chunk arrives; $first_row is available for header writing
public function openFile(array $first_row = []): void {}

// Called once per chunk
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
| `xml` | `Xml` | ✅ | `root_name`, `branch_name` | Both params required; element names for root and each record |

> **Flat formats (CSV, XLSX):** Complex field values (arrays from Grid, Relationship, Fluid fields) are JSON-encoded into a single cell string via an internal `flattenValue()` helper.  
> **Native formats (JSON, XML):** Complex values are output as nested structures.

### Example — streaming `Tsv` (tab-separated) format

```php
<?php

namespace Acme\StoreExport\Formats;

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

        if ($this->fp === false) {
            throw new \RuntimeException('TSV: could not open cache file for writing: ' . $this->path);
        }

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

Register in `addon.setup.php`:

```php
'export' => [
    'formats' => [
        'tsv' => \Acme\StoreExport\Formats\Tsv::class,
    ],
],
```

Once registered, the `tsv` key is available in any Export template tag:

```ee
{exp:export:entries
    channel="products"
    format="tsv"
    output="download"
    filename="products.tsv"
}
```

### CP Form Fields

Same contract as Sources (see [§4 CP Form Fields](#cp-form-fields)): override `getCpFields()` and the Tsv key shows up with its own fieldset wherever the Format dropdown is shown, with zero changes to Export's own form code.

```php
public function getCpFields(array $context = []): array
{
    return [
        ['name' => 'delimiter', 'type' => 'text', 'label' => 'tsv_field_delimiter', 'default' => "\t", 'maxlength' => 1],
    ];
}
```

One difference from Sources: format choice labels in the dropdown aren't lang-keyed by default the way source/output choices are (`CSV`, `Excel (XLSX)` are literal strings, not translated). If you want full control over how your format's label renders in the dropdown rather than a humanized fallback of its key, override `getCpLabel()` too:

```php
public function getCpLabel(): ?string
{
    return 'TSV';
}
```

Leave it unoverridden (it returns `null` by default) and the dropdown falls back to `lang('export_format_' . $key)` if that resolves to something, or a humanized version of the key otherwise.

---

## §6 Creating Modifiers

Modifiers transform individual field values. They run after the source produces each chunk and before the format writes it. Multiple modifiers can be chained per field using `|`.

**Base class:** `Mithra62\Export\Plugins\AbstractModifier`  
**Tag param:** `modify:field_name="modifier_key[param1][param2]"`

Once registered, a custom modifier is available in any Export template tag. See [§10](#10-using-third-party-plugins-with-built-in-tags).

### Contract

```php
public function process(mixed $value): mixed
```

Receive a value, return the transformed value. The type may change (e.g. `int` → `string` is fine).

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
| `replace_with[value]` | `ReplaceWith` | `with` | Replace the entire value with a literal string (default: `N/A`) |
| `uc_first` | `UcFirst` | — | `ucfirst()` on the value |
| `uc_words` | `UcWords` | — | `ucwords()` on the value |

### Example — `Truncate` modifier

```php
<?php

namespace Acme\StoreExport\Modifiers;

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

Register in `addon.setup.php`:

```php
'export' => [
    'modifiers' => [
        'truncate' => \Acme\StoreExport\Modifiers\Truncate::class,
    ],
],
```

Once registered, `truncate` is available in any Export template tag alongside built-in modifiers:

```ee
{exp:export:entries
    channel="blog"
    modify:title="truncate[80][…]"
    modify:author_id="replace_with[anonymous]"
    modify:entry_date="ee_date[%Y-%m-%d]"
    format="csv"
    output="download"
    filename="blog.csv"
}
```

---

## §7 Creating Output Destinations

Destinations receive the path to the generated export file and deliver it — to the browser, a directory, a remote service, etc.

**Base class:** `Mithra62\Export\Plugins\AbstractDestination`  
**Tag param:** `output="your_key"`

Once registered, a custom output is available in any Export template tag. See [§10](#10-using-third-party-plugins-with-built-in-tags).

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
| `$this->shouldDie()` | Read by `ExportService`; triggers `exit` if `true` |

### Built-in destinations

| Tag value | Class | Required params | Behaviour |
|---|---|---|---|
| `download` | `Download` | `filename` | Streams file to browser via `readfile()`; sets `force_exit = true`; exits after delivery |
| `local` | `Local` | `filename`, `path` | Copies file to a local directory; validates directory exists and is writable |

### Example — `S3` destination

```php
<?php

namespace Acme\StoreExport\Outputs;

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

Register in `addon.setup.php`:

```php
'export' => [
    'outputs' => [
        's3' => \Acme\StoreExport\Outputs\S3::class,
    ],
],
```

Once registered, `s3` is available in any Export template tag:

```ee
{exp:export:members
    format="csv"
    output="s3"
    output:bucket="my-exports-bucket"
    output:prefix="members/"
    filename="members-export.csv"
}
```

### CP Form Fields

Same contract as Sources and Formats: override `getCpFields()` and the `s3` key gets its own fieldset in the Output section.

```php
public function getCpFields(array $context = []): array
{
    return [
        ['name' => 'bucket', 'type' => 'text', 'label' => 'export_field_s3_bucket', 'required' => true],
        ['name' => 'prefix', 'type' => 'text', 'label' => 'export_field_s3_prefix'],
    ];
}
```

`filename` is the one exception: it's a field every output destination needs, so Export's own form renders it once, outside any per-output fieldset, rather than asking every Output class (built-in or third-party) to declare it independently. Don't declare a `filename` field in your own `getCpFields()` — it would just be a second, redundant field with the same stored key.

---

## §8 Creating Field Handlers

Field handlers process individual custom field values — date formatting, file URL resolution, relationship lookups, etc. The same handler is invoked for a given field type regardless of which source (Entries, Grid, Members) produces the row.

**Base class:** `Mithra62\Export\Plugins\AbstractField`  
**Discovered via:** `addon.setup.php` `export.fields` declaration (no namespace fallback for field handlers)

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
| `$entry_id` | `int` | The entry ID — or `row_id` in Grid context, or `member_id` in Members context |
| `$context` | `array` | Pre-fetched batch data passed by the source (see §9) |

**Return type convention:**
- Return a **scalar** for simple values (string, int, float)
- Return an **array** for complex values (relationships, grid rows, fluid instances)
- Flat formats (CSV, XLSX) JSON-encode arrays into a single cell via `flattenValue()`
- Native formats (JSON, XML) output arrays as nested structures

### Registering a handler

Declare field handlers in your `addon.setup.php` under `export.fields`. The key is the EE field type slug.

```php
// system/user/addons/store_export/addon.setup.php
return [
    'name'      => 'Store Export',
    'namespace' => 'Acme\\StoreExport',
    // ...
    'export' => [
        'fields' => [
            'rating'       => \Acme\StoreExport\Fields\Rating::class,
            'product_link' => \Acme\StoreExport\Fields\ProductLink::class,
        ],
    ],
];
```

`FieldsService` scans all installed add-ons via `ee('App')->getProviders()` on first use and merges all `export.fields` maps. Export's own built-in handlers form the baseline; third-party declarations are merged after and can override built-ins if needed.

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

namespace Acme\StoreExport\Fields;

use Mithra62\Export\Plugins\AbstractField;

class Rating extends AbstractField
{
    public function process(
        mixed $raw_value,
        array $field_info,
        int   $entry_id,
        array $context = []
    ): mixed {
        // Stored as an integer string "1"–"5"; cast and default to 0
        return $raw_value !== null ? (int) $raw_value : 0;
    }
}
```

### Example with context — field that resolves related data

```php
<?php

namespace Acme\StoreExport\Fields;

use Mithra62\Export\Plugins\AbstractField;

class ProductLink extends AbstractField
{
    public function process(
        mixed $raw_value,
        array $field_info,
        int   $entry_id,
        array $context = []
    ): mixed {
        if (empty($raw_value)) {
            return [];
        }

        // raw_value stores pipe-separated product IDs
        $product_ids = array_filter(explode('|', $raw_value));

        $result = ee()->db
            ->select('product_id, product_name, sku')
            ->from(ee()->db->dbprefix . 'store_products')
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

Handlers can branch based on which source invoked them using `$context['source_type']`. This is important when a handler would otherwise use `$field_info['field_id']` or the `$entry_id` argument — those values mean different things depending on the source context.

```php
public function process(
    mixed $raw_value,
    array $field_info,
    int   $entry_id,
    array $context = []
): mixed {
    $source = $context['source_type'] ?? 'entries';

    if ($source === 'grid') {
        // $entry_id here is the grid row_id, NOT the channel entry_id
        $real_entry_id = $context['entry_id']; // actual channel_titles.entry_id
        $row_id        = $context['row_id'];   // actual channel_grid_field_X.row_id
        $col_id        = $context['col_id'];   // actual grid_columns.col_id

    } elseif ($source === 'fluid') {
        // $entry_id here is the fluid instance_id, NOT the channel entry_id
        $real_entry_id  = $context['entry_id'];          // actual channel_titles.entry_id
        $instance_id    = $context['fluid_instance_id']; // fluid_field_data.id
        $fluid_field_id = $context['fluid_field_id'];    // parent fluid channel_fields.field_id

    } elseif ($source === 'member') {
        // $entry_id is the member_id
        $member_id = $context['member_id']; // same value, explicit key

    } else {
        // Entries source (source_type absent means entries)
        // $entry_id is the genuine channel_titles.entry_id
    }

    return $raw_value ?? '';
}
```

---

## §9 Field Handler Context Reference

The `$context` array passed to `AbstractField::process()` varies by source. ✅ = present and populated; — = not present.

| Context key | Type | Entries | Grid | Fluid | Members | Description |
|---|---|---|---|---|---|---|
| `source_type` | `string` | — | `'grid'` | `'fluid'` | `'member'` | Source identifier; absent means Entries |
| `rel_data` | `array` | ✅ | ✅ | ✅ | — | `[entry_id\|row_id\|instance_id][field_id\|col_id][] = child_entry_id` |
| `rel_cache` | `array` | ✅ | ✅ | ✅ | — | `[child_entry_id] = ['title' => ..., ...]` |
| `grid_data` | `array` | ✅ | — | ✅ | — | `[field_id][entry_id\|instance_id][] = raw grid row array` |
| `channel_fields` | `array` | ✅ | — | — | — | `[field_id] = field_info array` for the whole channel |
| `grid_columns` | `array` | ✅ | — | ✅ | — | `[field_id][col_id] = col_info array` |
| `fluid_instances` | `array` | ✅ | — | — | — | `[entry_id][fluid_field_id][] = fluid_field_data row` |
| `fluid_values` | `array` | ✅ | — | — | — | `[sub_field_id][field_data_id] = scalar value` |
| `fluid_grid_data` | `array` | ✅ | — | — | — | `[sub_field_id][fluid_instance_id][] = grid row` |
| `entry_id` | `int` | — | ✅ | ✅ | — | Actual `channel_titles.entry_id` (use when `$entry_id` arg = row_id / instance_id) |
| `row_id` | `int` | — | ✅ | — | — | Actual `channel_grid_field_X.row_id` |
| `col_id` | `int` | — | ✅ | — | — | Actual `grid_columns.col_id` |
| `fluid_instance_id` | `int` | — | — | ✅ | — | `fluid_field_data.id` (same value as `$entry_id` arg in Fluid context) |
| `fluid_field_id` | `int` | — | — | ✅ | — | Parent fluid field's `channel_fields.field_id` |
| `member_id` | `int` | — | — | — | ✅ | Actual `exp_members.member_id` (same as `$entry_id` arg in Members context) |

### `rel_data` key detail

The outer key is always the `$entry_id` argument — each source passes the most locally-scoped ID so relationship lookups stay row-safe:

```
Entries:  $context['rel_data'][$entry_id][$field_id][]    = $child_entry_id
Grid:     $context['rel_data'][$row_id][$col_id][]        = $child_entry_id
Fluid:    $context['rel_data'][$instance_id][$field_id][] = $child_entry_id
```

`Fields\Relationship` uses the `$entry_id` argument as the outer key in all three cases. The disambiguation works because each source passes the correct scope key as `$entry_id`.

---

## §10 Using Third-Party Plugins with Built-In Tags

Once a third-party add-on registers custom formats, outputs, or modifiers, those keys are immediately available as param values in **any** Export template tag, including the built-in first-party tags. No additional wiring is needed. Register once; use anywhere.

### Custom format with a built-in tag

```ee
{!-- Use the TSV format registered by store_export with the built-in Entries tag --}
{exp:export:entries
    channel="products"
    format="tsv"
    output="download"
    filename="products.tsv"
}
```

```ee
{!-- Use the TSV format with the built-in Members tag --}
{exp:export:members
    format="tsv"
    output="download"
    filename="members.tsv"
}
```

### Custom output with a built-in tag

```ee
{!-- Upload a CSV export directly to S3 --}
{exp:export:entries
    channel="blog"
    format="csv"
    output="s3"
    output:bucket="my-exports"
    output:prefix="blog/"
    filename="blog-{current_time format='%Y%m%d'}.csv"
}
```

### Custom modifier with a built-in tag

```ee
{!-- Use the third-party truncate modifier alongside built-in modifiers --}
{exp:export:entries
    channel="blog"
    format="csv"
    output="download"
    filename="blog.csv"
    modify:title="truncate[80][…]"
    modify:entry_date="ee_date[%Y-%m-%d]"
    modify:author_id="replace_with[anonymous]"
}
```

### Custom sources require a companion tag

Built-in Export tags hardcode their source key in PHP — there is no `source=` param you can pass from a template to override it. A custom source can only be invoked through a companion tag class in your add-on.

```ee
{!-- CORRECT: custom source invoked through its companion tag --}
{exp:store_export:orders
    status="complete"
    format="csv"
    output="download"
    filename="orders.csv"
}
```

```ee
{!-- CORRECT: combine your custom source tag with a third-party format and output --}
{exp:store_export:orders
    status="complete"
    format="tsv"
    output="s3"
    output:bucket="my-exports"
    output:prefix="orders/"
    filename="orders-{current_time format='%Y%m%d'}.tsv"
}
```

```ee
{!-- WRONG: built-in tags do not accept a source= param override --}
{exp:export:entries source="orders" ...}  ← this does not work
```

### Mixing and matching

Any registered source, format, output, and modifier can be freely combined, regardless of which add-on registered them:

| Tag | Source | Format | Output |
|---|---|---|---|
| `{exp:export:entries ...}` | Built-in | Third-party | Third-party |
| `{exp:export:members ...}` | Built-in | Built-in | Third-party |
| `{exp:store_export:orders ...}` | Third-party | Third-party | Third-party |
| `{exp:store_export:orders ...}` | Third-party | Built-in | Built-in |
