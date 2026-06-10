# Export — Complete Reference

**Version:** 1.0.0-beta.1  
**Author:** mithra62  
**Namespace:** `Mithra62\Export`

---

## Table of Contents

1. [Overview](#1-overview)
2. [How the Pipeline Works](#2-how-the-pipeline-works)
3. [Template Tags](#3-template-tags)
   - [Entries](#31-entries)
   - [Members](#32-members)
   - [Grid](#33-grid)
   - [Fluid](#34-fluid)
   - [Query (SQL)](#35-query-sql)
4. [Formats](#4-formats)
5. [Outputs (Destinations)](#5-outputs-destinations)
6. [Modifiers](#6-modifiers)
7. [Common Parameters](#7-common-parameters)
8. [Extending — Sources](#8-extending--sources)
9. [Extending — Formats](#9-extending--formats)
10. [Extending — Outputs](#10-extending--outputs)
11. [Extending — Modifiers](#11-extending--modifiers)
12. [Extending — Field Handlers](#12-extending--field-handlers)
13. [Field Handler Context Reference](#13-field-handler-context-reference)
14. [Control Panel](#14-control-panel)

---

## System Requirements

| Dependency | Minimum version |
|---|---|
| ExpressionEngine | 7.2 |
| PHP | 8.0 |
| [openspout/openspout](https://github.com/openspout/openspout) | 4.0 |

---

## 1. Overview

Export is an ExpressionEngine addon that exports data from multiple **sources** (channel entries, members, grid fields, raw SQL) through multiple **formats** (CSV, JSON, XLSX, XML) to multiple **destinations** (browser download, local filesystem). Each layer is independently extensible via a factory + strategy pattern, and any installed EE addon can register custom field handlers, sources, formats, modifiers, and outputs without modifying the Export codebase.

---

## 2. How the Pipeline Works

Every export tag fires this sequence:

```
Tag method (e.g. Entries::process())
  ↓ sets source:*, format:*, output:*, modify:* params
AbstractTag::compile()
  ↓
ExportService::build()
  ├─ if source->supportsStreaming()  →  buildStreaming()
  │     source->openStream()
  │     loop: source->nextChunk() → format->writeChunk()
  │     source->closeStream()
  │     format->finalizeFile()  →  path
  └─ else
        source->compile()       →  export_data[]
        format->compile()       →  path
  ↓
output->process(path)           →  download / copy / etc.
```

**Streaming sources** (Entries, Grid, Fluid, Members) process data in configurable chunks so memory stays constant regardless of dataset size. Members also lazy-loads custom field definitions — the `member_data` JOIN is skipped entirely when no custom member fields are configured.  
**Non-streaming sources** (SQL) load all rows into memory before writing.

### Param namespacing

Tag parameters are routed by prefix:

| Prefix | Consumed by |
|--------|-------------|
| *(none)* | Global / shared |
| `source:` | The active Source plugin |
| `format:` | The active Format plugin |
| `output:` | The active Output/Destination plugin |
| `modify:` | Modifier declarations |
| `search:` | Field-level search filters (Members, SQL) |

---

## 3. Template Tags

> **Example templates.** The `templates/` directory in the addon root contains ready-to-use EE template files for every tag and the most common parameter combinations. Each file includes inline comments identifying exactly what to change. Copy one into your site's templates directory and adjust the parameters to get started without writing anything from scratch.

> **Empty results.** When a source returns no data (e.g. a channel with no entries matching the filters), all export tags honour EE's standard `{if no_results}` conditional. If the block is present it is rendered; if absent the tag produces no output and no error. Download exports simply deliver nothing to the browser.
>
> ```ee
> {exp:export:entries channel="blog" format="csv" output="download" output:filename="blog.csv"}
> {if no_results}No entries matched your export criteria.{/if}
> {/exp:export:entries}
> ```

### 3.1 Entries

Exports channel entry rows. Each row contains standard entry columns plus every custom field assigned to the channel. Streams in configurable chunks for memory efficiency.

```ee
{exp:export:entries
    channel="blog"
    status="open"
    format="csv"
    output="download"
    output:filename="blog-entries.csv"
}
```

#### Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `channel` | ✅ | — | Channel short name or numeric ID |
| `format` | ✅ | — | Export format: `csv`, `json`, `xlsx`, `xml` |
| `output` | ✅ | — | Destination: `download`, `local` |
| `status` | | `open` | Entry status filter |
| `author_id` | | — | Filter by member ID |
| `limit` | | — | Maximum number of entries to export |
| `offset` | | `0` | Entry-level pagination offset |
| `chunk_size` | | `500` | Entries processed per streaming chunk |
| `relationship_fields` | | `title` | Pipe-separated fields to pull from related entries |
| `fields` | | — | Pipe-separated column **whitelist** — return only these columns, in this order |
| `exclude` | | — | Pipe-separated column **blacklist** — exclude these columns, return the rest |

#### Standard columns in every row

`entry_id`, `title`, `url_title`, `status`, `entry_date`, `expiration_date`, `author_id`, `edit_date`, `categories`

All custom channel fields follow, keyed by `field_name`.

#### Custom field handling

| EE Field Type | Export value |
|---------------|-------------|
| `text`, `textarea`, `rte`, etc. | String as-is |
| `date` | Unix timestamp integer |
| `file` | Absolute URL string |
| `relationship` | Array `[['entry_id' => X, 'title' => Y], ...]` |
| `grid` | Array of row objects `[['col_name' => 'val', ...], ...]` |
| `fluid_field` | Array of typed instances (see below) |

**Fluid field structure:**
```json
[
  {"type": "text",  "field_name": "intro", "order": 1, "value": "..."},
  {"type": "grid",  "field_name": "items",  "order": 2, "rows": [
    {"product": "Widget", "qty": "3"}
  ]}
]
```

**Flat formats (CSV, XLSX):** Arrays are JSON-encoded into a single cell string.  
**Native formats (JSON, XML):** Arrays are output as native nested structures.

#### Examples

```ee
{!-- Basic CSV download --}
{exp:export:entries
    channel="products"
    status="open"
    format="csv"
    output="download"
    output:filename="products.csv"
}

{!-- XLSX saved to server, paginated --}
{exp:export:entries
    channel="orders"
    status="open|closed"
    limit="1000"
    offset="0"
    format="xlsx"
    output="local"
    output:filename="orders.xlsx"
    output:path="/var/exports/"
}

{!-- JSON with date formatting and relationship resolution --}
{exp:export:entries
    channel="news"
    relationship_fields="title|url_title|field_author_bio"
    format="json"
    output="download"
    output:filename="news.json"
    modify:entry_date="ee_date[%Y-%m-%d]"
}

{!-- Exclude sensitive fields --}
{exp:export:entries
    channel="members_channel"
    exclude="field_ssn|field_dob"
    format="csv"
    output="download"
    output:filename="export.csv"
}
```

---

### 3.2 Members

Exports member rows in streaming chunks. Standard member columns are included alongside any custom member fields, which are routed through the same FieldsService handler pipeline as Entries, Grid, and Fluid. Custom field definitions are lazy-loaded — if no custom member fields exist the `member_data` join is skipped entirely.

```ee
{exp:export:members
    format="csv"
    output="download"
    output:filename="members.csv"
}
```

#### Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `format` | ✅ | — | `csv`, `json`, `xlsx`, `xml` |
| `output` | ✅ | — | `download`, `local` |
| `roles` | | — | Pipe-separated role IDs to filter by |
| `join_start` | | — | Filter join date from (any PHP-parseable date string) |
| `join_end` | | — | Filter join date to |
| `last_login_start` | | — | Filter last login from |
| `last_login_end` | | — | Filter last login to |
| `limit` | | — | Maximum number of members to export |
| `offset` | | `0` | Pagination offset |
| `chunk_size` | | `500` | Members per streaming chunk |
| `search:field_name` | | — | Filter by any member field value (see below) |
| `fields` | | — | Pipe-separated column **whitelist** — return only these columns, in this order |
| `exclude` | | — | Pipe-separated column **blacklist** — exclude these columns, return the rest |

#### Field-level search filters

Prefix any member column or custom field name with `search:` to filter:

```ee
{exp:export:members
    search:username="admin"
    search:my_custom_field="active"
    format="csv"
    output="download"
    output:filename="filtered.csv"
}
```

#### Examples

```ee
{!-- All members in roles 1 and 3 --}
{exp:export:members
    roles="1|3"
    format="xlsx"
    output="download"
    output:filename="members.xlsx"
}

{!-- Members who joined in 2025 --}
{exp:export:members
    join_start="2025-01-01"
    join_end="2025-12-31"
    format="json"
    output="local"
    output:path="/var/exports/"
    output:filename="members-2025.json"
}

{!-- Exclude password hash and private fields --}
{exp:export:members
    exclude="password|salt|m_field_private_notes"
    format="csv"
    output="download"
    output:filename="members-safe.csv"
}
```

---

### 3.3 Grid

Exports EE Grid field rows as a flat tabular dataset. Each exported row represents one grid row and carries entry-level context columns alongside all grid column values. Streams in chunks.

```ee
{exp:export:grid
    channel="products"
    field="variants"
    format="csv"
    output="download"
    output:filename="variants.csv"
}
```

#### Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `channel` | ✅ | — | Channel short name or numeric ID |
| `field` | ✅ | — | Grid field short name or numeric field_id |
| `format` | ✅ | — | `csv`, `json`, `xlsx`, `xml` |
| `output` | ✅ | — | `download`, `local` |
| `status` | | `open` | Entry status filter |
| `author_id` | | — | Filter entries by member ID |
| `entry_id` | | — | Export grid rows for a single entry only |
| `limit` | | — | Maximum number of **entries** to process. Each entry can have many grid rows — total output rows may be much higher. |
| `offset` | | `0` | Entry-level pagination offset |
| `chunk_size` | | `500` | Entries per streaming chunk |
| `relationship_fields` | | `title` | Fields to pull from relationship-column targets |
| `fields` | | — | Pipe-separated column **whitelist** — return only these columns, in this order |
| `exclude` | | — | Pipe-separated column **blacklist** — exclude these columns, return the rest |

#### Output shape

```
entry_id | entry_title | row_order | <col_name_1> | <col_name_2> | ...
1        | My Product  | 1         | Red          | Large        | ...
1        | My Product  | 2         | Blue         | Medium       | ...
2        | Other Prod  | 1         | Green        | Small        | ...
```

`limit` controls the number of **entries** processed, not grid rows. A channel with 10 entries each having 5 grid rows will produce up to 50 output rows.

#### Examples

```ee
{!-- Export all variants across all open products --}
{exp:export:grid
    channel="products"
    field="variants"
    format="xlsx"
    output="download"
    output:filename="variants.xlsx"
}

{!-- Single entry's grid rows --}
{exp:export:grid
    channel="products"
    field="variants"
    entry_id="42"
    format="json"
    output="download"
    output:filename="entry-42-variants.json"
}

{!-- Grid with relationship column; pull title and sku --}
{exp:export:grid
    channel="orders"
    field="line_items"
    relationship_fields="title|field_sku|field_price"
    format="csv"
    output="download"
    output:filename="line-items.csv"
}
```

---

### 3.4 Fluid

Exports EE Fluid field instances as a flat tabular dataset. Each exported row represents one content block (fluid instance) and carries entry-level context alongside the block's metadata and processed value. Streams in chunks.

All sub-field types are routed through the FieldsService handler pipeline:
- **Relationship sub-fields** — resolved via the `relationships` table (EE stores fluid relationship values keyed by `fluid_field_data_id`, not in `channel_data_field_X`)
- **Grid sub-fields** — resolved via `Fields/Grid`, including relationship columns nested within the grid
- **Date, File, and all other registered types** — routed through their respective field handlers
- **Unregistered types** — raw stored value

```ee
{exp:export:fluid
    channel="blog"
    field="page_builder"
    format="csv"
    output="download"
    output:filename="page-builder-blocks.csv"
}
```

#### Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `channel` | ✅ | — | Channel short name or numeric ID |
| `field` | ✅ | — | Fluid field short name or numeric field_id |
| `format` | ✅ | — | `csv`, `json`, `xlsx`, `xml` |
| `output` | ✅ | — | `download`, `local` |
| `status` | | `open` | Entry status filter |
| `author_id` | | — | Filter entries by member ID |
| `entry_id` | | — | Export instances for a single entry only |
| `limit` | | — | Maximum number of **entries** to process. Each entry can have many fluid instances — total output rows may be much higher. |
| `offset` | | `0` | Entry-level pagination offset |
| `chunk_size` | | `500` | Entries per streaming chunk |
| `relationship_fields` | | `title` | Pipe-separated fields to pull from related entries |
| `fields` | | — | Pipe-separated column **whitelist** — return only these columns, in this order |
| `exclude` | | — | Pipe-separated column **blacklist** — exclude these columns, return the rest |

#### Output shape

```
entry_id | entry_title  | instance_order | sub_field_id | sub_field_type | sub_field_label  | value
1        | My Blog Post | 1              | 12           | text           | Introduction     | "Welcome..."
1        | My Blog Post | 2              | 14           | grid           | Feature Blocks   | [{"heading":"..."}]
1        | My Blog Post | 3              | 15           | relationship   | Related Articles | [{"entry_id":5,"title":"..."}]
```

`limit` controls the number of **entries** processed, not fluid instances. One entry with five blocks produces five output rows.

#### Examples

```ee
{!-- Export all page builder blocks across all open entries --}
{exp:export:fluid
    channel="blog"
    field="page_builder"
    format="xlsx"
    output="download"
    output:filename="page-builder.xlsx"
}

{!-- Single entry's blocks --}
{exp:export:fluid
    channel="blog"
    field="page_builder"
    entry_id="42"
    format="json"
    output="download"
    output:filename="entry-42-blocks.json"
}

{!-- Only the columns you need, in a specific order --}
{exp:export:fluid
    channel="blog"
    field="page_builder"
    fields="entry_title|instance_order|sub_field_label|value"
    relationship_fields="title|field_url"
    format="csv"
    output="download"
    output:filename="blocks-summary.csv"
}
```

---

### 3.5 Query (SQL)

Exports the result of a raw SQL query. Column names in the query result become the export column headers.

```ee
{exp:export:query
    sql="SELECT member_id, username, email FROM exp_members WHERE group_id = 1"
    format="csv"
    output="download"
    output:filename="admins.csv"
}
```

#### Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `sql` | ✅ | — | Full SQL query string |
| `format` | ✅ | — | `csv`, `json`, `xlsx`, `xml` |
| `output` | ✅ | — | `download`, `local` |
| `fields` | | — | Pipe-separated column **whitelist** — return only these columns, in this order |
| `exclude` | | — | Pipe-separated column **blacklist** — exclude these columns, return the rest |

> **Security:** Only `SELECT` statements are accepted. Queries containing semicolons, SQL comment sequences (`--`, `/* */`), or destructive keywords (`INSERT`, `UPDATE`, `DELETE`, `DROP`, `TRUNCATE`, `ALTER`, `CREATE`, `REPLACE`, `CALL`, `EXEC`) are rejected at validation time. The query runs with the database credentials configured for your EE installation, so avoid interpolating user-supplied values directly into the query string.
>
> **Control Panel:** In the CP, the SQL source is restricted to Super Admins.

---

## 4. Formats

The `format` parameter selects the output format. Format plugins live in `Formats/` and are resolved by converting the format name to StudlyCase (`csv` → `Csv`).

| Format | `format=` value | File type | Notes |
|--------|----------------|-----------|-------|
| CSV | `csv` | `.csv` | Streaming write; arrays JSON-encoded per cell |
| JSON | `json` | `.json` | Streaming write; nested arrays output natively |
| XLSX | `xlsx` | `.xlsx` | Streaming write via OpenSpout; constant memory |
| XML | `xml` | `.xml` | Streaming write; nested arrays recurse into child nodes |

### Format-specific parameters

All format params are prefixed with `format:`.

#### XLSX (`format="xlsx"`)

| Parameter | Default | Description |
|-----------|---------|-------------|
| `format:sheet_name` | `Export` | Name of the worksheet tab |

#### XML (`format="xml"`)

| Parameter | Default | Description |
|-----------|---------|-------------|
| `format:root_name` | — | XML root element name (**required**) |
| `format:branch_name` | — | Wrapping element for each data row (**required**) |

---

## 5. Outputs (Destinations)

The `output` parameter selects where the finished export file is delivered. Output plugins live in `Output/` and are resolved by StudlyCase conversion.

### `output="download"`

Streams the file to the browser as a download and exits PHP immediately after.

| Parameter | Required | Description |
|-----------|----------|-------------|
| `output:filename` | ✅ | Filename presented to the browser |

```ee
output="download"
output:filename="export.csv"
```

### `output="local"`

Copies the file to a path on the server filesystem.

| Parameter | Required | Description |
|-----------|----------|-------------|
| `output:filename` | ✅ | Destination filename |
| `output:path` | ✅ | Absolute directory path (must exist and be writable) |

```ee
output="local"
output:path="/var/www/exports/"
output:filename="nightly-export.csv"
```

---

## 6. Modifiers

Modifiers post-process individual column values after the source has produced the row. Any number of modifiers can be applied to any column; they are declared as `modify:column_name="modifier_name"`.

**Syntax:**

```ee
modify:column_name="modifier_name"
modify:column_name="modifier_name[param1][param2]"
modify:column_name="modifier_a|modifier_b[param]"
```

Modifiers chain left to right — the output of each is the input to the next.

### Built-in modifiers

#### `ee_date`

Formats a Unix timestamp using EE's localisation engine.

```ee
modify:entry_date="ee_date[%Y-%m-%d]"
modify:join_date="ee_date[%F %j, %Y]"
```

| Param | Description |
|-------|-------------|
| `format` | EE date format string (e.g. `%Y-%m-%d`, `%g:%i %a`) |

#### `ee_decrypt`

Decrypts a value that was encrypted by EE's encryption service.

```ee
modify:encrypted_field="ee_decrypt"
```

#### `replace_with`

Replaces the entire column value with a static literal string.

```ee
modify:sensitive_column="replace_with[REDACTED]"
```

| Param | Description |
|-------|-------------|
| `with` | Replacement value |

#### `uc_first`

Uppercases the first character of the value.

```ee
modify:username="uc_first"
```

#### `uc_words`

Uppercases the first letter of each word.

```ee
modify:display_name="uc_words"
```

### Chaining example

```ee
{exp:export:members
    format="csv"
    output="download"
    output:filename="members.csv"
    modify:join_date="ee_date[%Y-%m-%d]"
    modify:last_visit="ee_date[%Y-%m-%d]"
    modify:display_name="uc_words"
    modify:bio="replace_with[REDACTED]"
}
```

---

## 7. Common Parameters

These parameters are available on all tags.

| Parameter | Description |
|-----------|-------------|
| `fields="col_a\|col_b"` | **Whitelist.** Return *only* the named columns, in the order listed. When present, `exclude` is ignored. Also controls output column order. |
| `exclude="col_a\|col_b"` | **Blacklist.** Remove the named columns from every output row; return everything else. Only applied when `fields` is absent. |
| `modify:col="modifier"` | Apply one or more modifiers to a column (see §6). |

**Priority rule:** `fields` → `exclude` → return all columns.

```ee
{!-- Return only two columns, in a specific order --}
{exp:export:entries
    channel="blog"
    fields="title|entry_date"
    format="csv"
    output="download"
    output:filename="titles.csv"
}

{!-- Return everything except two sensitive columns --}
{exp:export:members
    exclude="password|salt"
    format="csv"
    output="download"
    output:filename="members.csv"
}
```

---

## 8. Extending — Sources

> **Extension requires a standalone EE add-on.** Source classes and their companion tag classes must live in your own separately-installed EE add-on — not inside Export. See `EXTENDING.md` for a complete quickstart walkthrough.

A Source is responsible for fetching data and returning it as a 2D array of rows. Extend `AbstractSource` and register your class in your add-on's `addon.setup.php` under `export.sources`.

**Base class:** `Mithra62\Export\Plugins\AbstractSource`

### Registration

```php
// system/user/addons/store_export/addon.setup.php
'export' => [
    'sources' => [
        'orders' => \Acme\StoreExport\Sources\Orders::class,
    ],
],
```

### Minimal (non-streaming) source

```php
<?php

namespace Acme\StoreExport\Sources;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Orders extends AbstractSource
{
    // Validation rules — EE's Validation service syntax
    protected array $rules = [
        'source' => 'required',
    ];

    public function compile(): static
    {
        $query = ee()->db
            ->select('order_id, customer_email, total, status, created_at')
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

### Streaming source

Override `supportsStreaming()` and the three streaming methods for large datasets:

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
    }

    public function nextChunk(): array
    {
        $result = ee()->db
            ->from(ee()->db->dbprefix . 'store_orders')
            ->limit($this->stream_chunk_size, $this->stream_offset)
            ->get();

        if ($result->num_rows() === 0) {
            return [];
        }

        $rows = [];
        foreach ($result->result_array() as $row) {
            $rows[] = $this->cleanFields($row);
        }

        $this->stream_offset += count($rows);
        return $rows;
    }

    public function closeStream(): void {}
}
```

### Custom validation

```php
use ExpressionEngine\Service\Validation\Validator;

protected function getValidator(): Validator
{
    $validator = parent::getValidator();
    $validator->defineRule('validStatus', function ($key, $value) {
        return in_array($value, ['pending', 'complete', 'refunded'])
            ? true
            : 'invalid status value';
    });
    return $validator;
}

protected array $rules = [
    'source' => 'required',
    'status' => 'required|validStatus',
];
```

### Companion tag

**Custom sources require a companion tag class in your add-on.** The built-in Export tags (`{exp:export:entries}`, etc.) hardcode their own source key — there is no template-level `source=` override. Your add-on must supply a tag class that sets the source key and calls `$this->compile()`.

```php
<?php

namespace Acme\StoreExport\Tags;

use Mithra62\Export\Tags\AbstractTag;

class Orders extends AbstractTag
{
    public function process(): void
    {
        $params = $this->params();
        $params['source']          = 'orders'; // matches key in addon.setup.php
        $params['source:status']   = $this->param('status', 'complete');
        $params['source:limit']    = $this->param('limit');
        $this->compile($params);
    }
}
```

Usage (the tag is on **your add-on**, not on `export`):

```ee
{exp:store_export:orders
    status="complete"
    limit="500"
    format="csv"
    output="download"
    filename="orders.csv"
    {if no_results}No orders to export.{/if}
}
```

---

## 9. Extending — Formats

> **Extension requires a standalone EE add-on.** Format classes must live in your own separately-installed EE add-on. See `EXTENDING.md` for full details.

A Format receives the source data and writes it to a file, returning the absolute path. Extend `AbstractFormat` and register your class in your add-on's `addon.setup.php` under `export.formats`.

**Base class:** `Mithra62\Export\Plugins\AbstractFormat`

Once registered, your format key is available as the `format=` param in **any** Export template tag — including the built-in first-party tags.

### Registration

```php
// system/user/addons/store_export/addon.setup.php
'export' => [
    'formats' => [
        'tsv' => \Acme\StoreExport\Formats\Tsv::class,
    ],
],
```

### Non-streaming format

```php
<?php

namespace Acme\StoreExport\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;

class Tsv extends AbstractFormat
{
    public function compile(AbstractSource $source): string
    {
        $rows = $source->getExportData();
        $path = $this->getCacheDirPath() . $this->getCacheFilename() . '.tsv';

        $fp = fopen($path, 'w');
        fputcsv($fp, array_keys(reset($rows)), "\t");
        foreach ($rows as $row) {
            fputcsv($fp, array_values($row), "\t");
        }
        fclose($fp);

        return $path;
    }
}
```

### Streaming format

```php
<?php

namespace Acme\StoreExport\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;

class Tsv extends AbstractFormat
{
    protected string $path           = '';
    protected mixed  $fp             = null;
    protected bool   $header_written = false;

    public function supportsStreaming(): bool { return true; }

    public function compile(AbstractSource $source): string
    {
        // Called for non-streaming fallback
        $this->openFile();
        $this->writeChunk($source->getExportData());
        return $this->finalizeFile();
    }

    public function openFile(array $first_row = []): void
    {
        $this->path = $this->getCacheDirPath() . $this->getCacheFilename() . '.tsv';
        $this->fp   = fopen($this->path, 'w');
        if (!empty($first_row)) {
            fputcsv($this->fp, array_keys($first_row), "\t");
            $this->header_written = true;
        }
    }

    public function writeChunk(array $rows): void
    {
        if (empty($rows)) return;

        if (!$this->header_written) {
            fputcsv($this->fp, array_keys(reset($rows)), "\t");
            $this->header_written = true;
        }

        foreach ($rows as $row) {
            fputcsv($this->fp, array_values($row), "\t");
        }
    }

    public function finalizeFile(): string
    {
        fclose($this->fp);
        return $this->path;
    }
}
```

### Using a custom format with built-in tags

Once registered, the `tsv` key is a drop-in replacement for any built-in format value:

```ee
{!-- Custom TSV format with the built-in Entries tag --}
{exp:export:entries
    channel="products"
    format="tsv"
    output="download"
    filename="products.tsv"
}

{!-- Custom TSV format with the built-in Members tag --}
{exp:export:members
    format="tsv"
    output="download"
    filename="members.tsv"
}
```

---

## 10. Extending — Outputs

> **Extension requires a standalone EE add-on.** Output classes must live in your own separately-installed EE add-on. See `EXTENDING.md` for full details.

An Output plugin receives the path to the finished export file and delivers it somewhere. Extend `AbstractDestination` and register your class in your add-on's `addon.setup.php` under `export.outputs`.

**Base class:** `Mithra62\Export\Plugins\AbstractDestination`

Once registered, your output key is available as the `output=` param in **any** Export template tag — including the built-in first-party tags.

### Registration

```php
// system/user/addons/store_export/addon.setup.php
'export' => [
    'outputs' => [
        's3' => \Acme\StoreExport\Outputs\S3::class,
    ],
],
```

### Example — S3 destination

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
            return preg_match('/^[a-z0-9\-\.]+$/', $value)
                ? true
                : 'invalid bucket name';
        });
        return $validator;
    }
}
```

Setting `protected bool $force_exit = true` causes PHP to exit immediately after `process()` returns — use this for browser downloads.

### Using a custom output with built-in tags

Once registered, the `s3` key is a drop-in replacement for any built-in output value:

```ee
{!-- Upload a CSV export directly to S3 using the built-in Entries tag --}
{exp:export:entries
    channel="blog"
    format="csv"
    output="s3"
    output:bucket="my-exports"
    output:prefix="daily/"
    filename="entries.csv"
}

{!-- Combine a third-party output with a third-party source tag --}
{exp:store_export:orders
    status="complete"
    format="csv"
    output="s3"
    output:bucket="order-exports"
    filename="orders.csv"
}
```

---

## 11. Extending — Modifiers

> **Extension requires a standalone EE add-on.** Modifier classes must live in your own separately-installed EE add-on. See `EXTENDING.md` for full details.

A Modifier transforms a single column value. Modifiers chain, so the output of one is the input to the next. Extend `AbstractModifier` and register your class in your add-on's `addon.setup.php` under `export.modifiers`.

**Base class:** `Mithra62\Export\Plugins\AbstractModifier`

Once registered, your modifier key is available in the `modify:col=` param in **any** Export template tag — including the built-in first-party tags — and can be chained with other modifiers using `|`.

### Registration

```php
// system/user/addons/store_export/addon.setup.php
'export' => [
    'modifiers' => [
        'truncate'   => \Acme\StoreExport\Modifiers\Truncate::class,
        'strip_tags' => \Acme\StoreExport\Modifiers\StripTags::class,
    ],
],
```

### Declaring parameters

Bracket values in the tag (`modifier[val1][val2]`) are mapped positionally to `$params`:

```php
protected array $params = ['length', 'suffix'];
// modify:bio="truncate[120][…]"
// → getParam('length') = '120'
// → getParam('suffix') = '…'
```

### Example — `Truncate`

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

### Example — `StripTags`

```php
<?php

namespace Acme\StoreExport\Modifiers;

use Mithra62\Export\Plugins\AbstractModifier;

class StripTags extends AbstractModifier
{
    public function process(mixed $value): mixed
    {
        return strip_tags((string) $value);
    }
}
```

### Using custom modifiers with built-in tags

Once registered, custom modifiers chain freely with built-in modifiers:

```ee
{!-- Third-party truncate + built-in ee_date, in the built-in Entries tag --}
{exp:export:entries
    channel="blog"
    format="csv"
    output="download"
    filename="blog.csv"
    modify:title="truncate[80][…]"
    modify:body="strip_tags|truncate[200]"
    modify:entry_date="ee_date[%Y-%m-%d]"
}
```

> **Naming:** The tag value is resolved with StudlyCase, so `strip_tags` resolves to `StripTags`, `uc_first` to `UcFirst`, etc.

---

## 12. Extending — Field Handlers

> **Extension requires a standalone EE add-on.** Field handler classes must live in your own separately-installed EE add-on. See `EXTENDING.md` for full details.

Field handlers process individual custom field values — date formatting, file URL resolution, relationship resolution, etc. The same handler is invoked for that field type regardless of which source (Entries, Grid, Members) produces the row.

### Registration

Any installed EE add-on can register handlers in its own `addon.setup.php` under the `export.fields` key. The key is the EE field type slug.

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

`FieldsService` scans all installed addons via `ee('App')->getProviders()` on first use and merges all `export.fields` maps. Export's own built-in handlers form the baseline; third-party declarations are merged after and can override built-ins.

### The `AbstractField` contract

```php
<?php

namespace Mithra62\Export\Plugins;

abstract class AbstractField
{
    /**
     * @param mixed $raw_value   Raw value from channel_data.field_id_X
     *                           (or member_data.m_field_id_X for members)
     * @param array $field_info  Field definition:
     *                             field_id, field_name, field_type,
     *                             field_label, field_settings (decoded array)
     * @param int   $entry_id    Entry ID — or row_id in Grid context,
     *                           or member_id in Members context
     * @param array $context     Pre-fetched batch data (see §13)
     */
    abstract public function process(
        mixed $raw_value,
        array $field_info,
        int   $entry_id,
        array $context = []
    ): mixed;
}
```

**Return type convention:**
- Return a **scalar** (string, int, float, bool) for simple values
- Return an **array** for complex values (relationships, grid rows, fluid instances)
- Native formats (JSON, XML) will receive the array directly
- Flat formats (CSV, XLSX) will call their `flattenValue()` helper which JSON-encodes arrays into a single cell string

### Minimal example — `Rating` field type

```php
<?php

namespace Acme\StoreExport\Fields;

use Mithra62\Export\Plugins\AbstractField;

class Rating extends AbstractField
{
    public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
    {
        // raw_value is stored as "3" (integer string 1–5)
        return $raw_value !== null ? (int) $raw_value : 0;
    }
}
```

### Example with context — custom relationship-style field

```php
<?php

namespace Acme\StoreExport\Fields;

use Mithra62\Export\Plugins\AbstractField;

class ProductLink extends AbstractField
{
    public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
    {
        if (empty($raw_value)) {
            return [];
        }

        // Resolve linked product IDs from a custom table
        $product_ids = explode('|', $raw_value);
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

Third-party handlers can branch based on which source invoked them using `$context['source_type']`:

```php
public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
{
    $source = $context['source_type'] ?? 'entries';

    if ($source === 'grid') {
        // $entry_id is the grid row_id, NOT the channel entry_id
        // Use $context['entry_id'] for the actual channel entry
        // Use $context['row_id']   for the grid row PK
        // Use $context['col_id']   for the grid column ID
        $real_entry_id = $context['entry_id'];

    } elseif ($source === 'member') {
        // $entry_id is the member_id
        // Use $context['member_id'] (same value, explicit key)
        $member_id = $context['member_id'];

    } else {
        // Default: Entries source
        // $entry_id is the genuine channel_titles.entry_id
        // $field_info['field_id'] is the genuine channel_fields.field_id
    }

    return $raw_value ?? '';
}
```

### Built-in field handlers

| Field Type | Handler Class | Behaviour |
|------------|--------------|-----------|
| `date` | `Fields\Date` | Casts raw Unix timestamp to `int` |
| `file` | `Fields\File` | Converts `{filedir_N}filename` token to absolute URL |
| `relationship` | `Fields\Relationship` | Returns `[['entry_id' => X, 'title' => Y], ...]` using batch-pre-fetched `rel_cache` |
| `grid` | `Fields\Grid` | Returns array of mapped row objects using batch-pre-fetched `grid_data` |
| `fluid_field` | `Fields\FluidField` | Returns typed instance array using batch-pre-fetched fluid data |

---

## 13. Field Handler Context Reference

The `$context` array passed to `AbstractField::process()` varies by source. Keys present in a given source are marked ✅; absent keys are marked —.

| Context key | Type | Entries | Grid | Members | Description |
|-------------|------|---------|------|---------|-------------|
| `source_type` | `string` | — | `'grid'` | `'member'` | Source identifier; absent means Entries |
| `rel_data` | `array` | ✅ | ✅ | — | `[entry_id\|row_id][field_id\|col_id][] = child_entry_id` |
| `rel_cache` | `array` | ✅ | ✅ | — | `[child_entry_id] = ['title' => ...]` (+ any `relationship_fields` requested) |
| `grid_data` | `array` | ✅ | — | — | `[field_id][entry_id][] = raw grid row array` |
| `channel_fields` | `array` | ✅ | — | — | `[field_id] = field_info array` for the whole channel |
| `grid_columns` | `array` | ✅ | — | — | `[field_id][col_id] = col_info array` |
| `fluid_instances` | `array` | ✅ | — | — | `[entry_id][fluid_field_id][] = fluid_field_data row` |
| `fluid_values` | `array` | ✅ | — | — | `[sub_field_id][field_data_id] = scalar value` |
| `fluid_grid_data` | `array` | ✅ | — | — | `[sub_field_id][fluid_instance_id][] = grid row` |
| `entry_id` | `int` | — | ✅ | — | Actual `channel_titles.entry_id` (Grid source only; use when `$entry_id` arg = row_id) |
| `row_id` | `int` | — | ✅ | — | Actual `channel_grid_field_X.row_id` |
| `col_id` | `int` | — | ✅ | — | Actual `grid_columns.col_id` |
| `member_id` | `int` | — | — | ✅ | Actual `exp_members.member_id` (same as `$entry_id` arg in Members context) |

### `rel_data` shape detail

```
Entries:  $context['rel_data'][$entry_id][$field_id][] = $child_entry_id
Grid:     $context['rel_data'][$row_id][$col_id][]     = $child_entry_id
```

The structure is identical — only the scope of the outer key differs. `Fields\Relationship` uses `$entry_id` (the argument) as the outer key in both cases, which resolves correctly in both contexts because Grid passes `$row_id` as `$entry_id`.

---

## 14. Control Panel

Export ships with a native ExpressionEngine Control Panel. The CP provides full CRUD management and one-click execution of saved export configurations — every option available through template tags is accessible through the form UI without writing any template code.

### Requirements

- ExpressionEngine meeting the [System Requirements](#system-requirements); uses the `ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute` routing paradigm
- The addon must be installed (or re-installed after upgrading from a version that lacked CP support) so the `exp_export_configurations` database table is created

### Accessing the CP

Navigate to **Add-Ons → Export** from the EE Control Panel. You must have the `can_access_addons` permission.

### Saved Configurations

Each saved configuration stores:

| Field | Description |
|-------|-------------|
| Name | Human-readable label shown in the index table |
| Source | Which data source to export (`entries`, `members`, `grid`, `fluid`, `sql`) |
| Settings | All source parameters, format settings, output settings, column selection, and modifiers serialised as JSON |

Configurations are site-aware — each EE site maintains its own list.

### Index Page

The index table lists all saved configurations for the current site. Each row shows the name, source, format, output type, and creation date alongside three toolbar actions:

| Action | Behaviour |
|--------|-----------|
| **Run** | Executes the configuration immediately. `download` output streams the file to the browser; `local` output saves to the configured path and shows a success alert. |
| **Edit** | Opens the edit form with all fields pre-populated from the stored settings. |
| **Delete** | Opens a confirmation form with a toggle to confirm removal. Submitting without toggling the confirmation does nothing. **Restricted to Super Admins.** |

### Create / Edit Form

The form is divided into sections that mirror the template tag parameter namespaces.

#### Section 1 — Identity

| Field | Input type | Required | Maps to |
|-------|-----------|----------|---------|
| Name | Text | ✅ | (stored separately; used for display only) |
| Source | Select | ✅ | `source` param; controls which source-specific section is shown |

#### Section 2 — Source Options

Only the fields for the selected source are visible (the others are present but hidden and not submitted). All fields map to `source:*` parameters.

**Entries**

| Field | Input type | Maps to |
|-------|-----------|---------|
| Channel | Select | `source:channel` |
| Status | Select (`open` / `closed` / `all`) | `source:status` |
| Author ID | Short text | `source:author_id` |
| Entry ID | Text (pipe-separated) | `source:entry_id` |
| Limit | Short text | `source:limit` |
| Offset | Short text | `source:offset` |
| Chunk Size | Short text | `source:chunk_size` |
| Relationship Fields | Text (pipe-separated) | `source:relationship_fields` |

**Members**

| Field | Input type | Maps to |
|-------|-----------|---------|
| Roles | Checkboxes | `source:roles` (stored pipe-separated) |
| Join Start | Date (`YYYY-MM-DD`) | `source:join_start` |
| Join End | Date (`YYYY-MM-DD`) | `source:join_end` |
| Last Login Start | Date (`YYYY-MM-DD`) | `source:last_login_start` |
| Last Login End | Date (`YYYY-MM-DD`) | `source:last_login_end` |
| Limit | Short text | `source:limit` |
| Offset | Short text | `source:offset` |
| Chunk Size | Short text | `source:chunk_size` |

> **Date fields:** Dates are stored as `YYYY-MM-DD` strings and accepted by `strtotime()` at run time, so they work identically to date strings passed directly in template tags.

**Grid**

Same fields as Entries plus:

| Field | Input type | Maps to |
|-------|-----------|---------|
| Channel | Select | `source:channel` |
| Grid Field | Select (AJAX-populated when channel changes) | `source:field` |

**Fluid**

Same fields as Entries plus:

| Field | Input type | Maps to |
|-------|-----------|---------|
| Channel | Select | `source:channel` |
| Fluid Field | Select (AJAX-populated when channel changes) | `source:field` |

**SQL**

| Field | Input type | Maps to |
|-------|-----------|---------|
| SQL Query | Textarea | `source:sql` |

> **Super Admin only.** The SQL source is restricted to Super Admins in the CP. Attempting to save a configuration with `source=sql` as a non-Super-Admin returns an inline validation error. The restriction applies only to the CP form; template tags are not affected.

#### Section 3 — Column Selection

Three mutually exclusive radio options control the `fields` / `exclude` parameters:

| Radio | Behaviour |
|-------|-----------|
| **All columns** | `fields` and `exclude` are both empty; source returns every column |
| **Include only (whitelist)** | An AJAX-loaded checkbox list appears; checked columns populate `fields` |
| **Exclude (blacklist)** | An AJAX-loaded checkbox list appears; checked columns populate `exclude` |

The available column list is fetched live from the server via the `ajax` route using the currently selected source and source parameters. Changing the source or channel triggers an automatic refresh.

#### Section 4 — Format

| Field | Input type | Maps to |
|-------|-----------|---------|
| Format | Select (`csv` / `json` / `xlsx` / `xml`) | `format` |

Format-specific options appear below the select based on the chosen format.

**CSV**

| Field | Input type | Maps to |
|-------|-----------|---------|
| Separator | Short text (1 char) | `format:separator` |
| Enclosure | Short text (1 char) | `format:enclosure` |
| Escape | Short text (1 char) | `format:escape` |
| Newline | Select (LF / CRLF / CR) | `format:newline` |

**XLSX**

| Field | Input type | Maps to |
|-------|-----------|---------|
| Bold column headers | Toggle | `format:bold_cols` |
| Sheet name | Text | `format:sheet_name` |

**XML**

| Field | Input type | Required | Maps to |
|-------|-----------|----------|---------|
| Root element name | Text | ✅ | `format:root_name` |
| Branch element name | Text | ✅ | `format:branch_name` |

#### Section 5 — Output

| Field | Input type | Maps to |
|-------|-----------|---------|
| Output | Select (`download` / `local`) | `output` |
| Filename | Text | `output:filename` |
| Path | Text (shown only when output = `local`) | `output:path` |

#### Section 6 — Modifiers

A dynamic table of column → modifier-chain pairs. Each row maps to a `modify:{column}="{chain}"` template tag parameter. Rows can be added or removed without a page reload. Modifier chain syntax is identical to template tags — e.g. `ee_date[%Y-%m-%d]|uc_first`.

### Database Schema

Saved configurations are stored in `exp_export_configurations`:

```sql
CREATE TABLE `exp_export_configurations` (
    `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `site_id`    INT(10) UNSIGNED NOT NULL DEFAULT 1,
    `name`       VARCHAR(255)     NOT NULL,
    `source`     VARCHAR(50)      NOT NULL,
    `settings`   MEDIUMTEXT,
    `created_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `site_id` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

The `settings` column is a JSON object whose keys follow the same `source:*` / `format:*` / `output:*` / `modify:*` namespacing as template tag parameters. `fields` and `exclude` are stored as arrays.

### CP Architecture (Developer Notes)

The CP uses the EE addon routing paradigm:

- **Gateway:** `mcp.export.php` — minimal class extending `ExpressionEngine\Service\Addon\Mcp`
- **Routes:** `ControlPanel/Routes/` — one class per URL segment, auto-discovered via `Str::studly()`; all extend a shared `AbstractRoute` that enforces `can_access_addons`
- **Views:** `views/index.php` and `views/form.php` — use `ee:_shared/table` and `ee:_shared/form` respectively; no custom HTML renderers
- **Model:** `Models/ExportConfiguration` — standard EE Model with typed columns and JSON encode/decode helpers
- **Service:** `Services/CpService` — registered as a singleton (`ee('export:CpService')`); provides channel lists, role lists, column introspection, settings ↔ POST conversion, and form section building
- **Validation bridge:** `Services/CpValidationBridge` — bridges driver-level EE Validation rules into CI form_validation so they surface as inline fieldset errors in the shared form. On every Create/Edit POST, the bridge instantiates the active source, format, and output drivers, calls each driver's `validate()` (running all `$rules` and custom `getValidator()` closures), maps errors from unprefixed driver param names back to CP field names, and injects them directly into CI's `$_field_data`. EE's `form_error()` / `form_error_class()` then render them inline without any separate `$errors` view variable. **Third-party plugin developers:** any custom rule you register via `$validator->defineRule()` inside your plugin's `getValidator()` is automatically run in the CP form — no extra wiring required.
- **AJAX endpoint:** `ControlPanel/Routes/Ajax.php` — handles `action=columns` (available export columns for a source config) and `action=fields` (Grid/Fluid field selects populated when a channel is chosen)
- **Permission gates:** `AbstractRoute` exposes a `requireSuperAdmin()` helper. The Delete route calls it unconditionally; the Create/Edit routes gate SQL source access via inline form validation.
