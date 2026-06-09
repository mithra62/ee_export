# Export for ExpressionEngine

A flexible, streaming-capable data export addon for ExpressionEngine. Export channel entries, members, grid field rows, or the results of any SQL query to CSV, JSON, XLSX, or XML — and deliver the file as a browser download or save it to the server filesystem.

Every layer of the pipeline is independently extensible. Third-party addons can ship their own sources, formats, outputs, modifiers, and field handlers with a single `addon.setup.php` declaration and zero changes to Export's codebase.

---

## Requirements

| Dependency | Minimum version |
|---|---|
| ExpressionEngine | 6.0 |
| PHP | 8.0 |
| [openspout/openspout](https://github.com/openspout/openspout) | 4.0 |

---

## Installation

1. Copy the `export` directory to `system/user/addons/`.
2. Run `composer install` inside the addon directory to install [OpenSpout](https://github.com/openspout/openspout) (used for constant-memory XLSX writing):
   ```bash
   cd system/user/addons/export
   composer install
   ```
3. In the ExpressionEngine control panel, go to **Add-On Manager** and install **Export**.

---

## Quick Start

Every export is triggered by a template tag. At minimum you need a **source**, a **format**, and an **output**:

```ee
{exp:export:entries
    channel="blog"
    format="csv"
    output="download"
    output:filename="blog-entries.csv"
}
```

Calling this tag from a template URL immediately streams a CSV download to the browser.

---

## Template Tags

### Entries

Export channel entries with all custom fields.

```ee
{exp:export:entries
    channel="products"
    status="open"
    limit="500"
    format="xlsx"
    output="download"
    output:filename="products.xlsx"
    output:bold_cols="y"
}
```

Complex fields (Grid, Relationship, Fluid) are serialised as JSON in flat formats (CSV, XLSX) and output as native nested structures in JSON and XML.

### Members

Export member rows including custom member fields.

```ee
{exp:export:members
    roles="1|3"
    join_start="2024-01-01"
    join_end="2024-12-31"
    format="csv"
    output="download"
    output:filename="members-2024.csv"
}
```

### Grid

Export every row of an EE Grid field as a flat table. Each output row carries entry context (`entry_id`, `entry_title`, `row_order`) alongside the grid column values.

```ee
{exp:export:grid
    channel="products"
    field="variants"
    format="csv"
    output="download"
    output:filename="variants.csv"
}
```

### Query (SQL)

Export the result of any SQL query directly.

```ee
{exp:export:query
    sql="SELECT member_id, username, email FROM exp_members WHERE role_id = 1"
    format="json"
    output="download"
    output:filename="admins.json"
}
```

---

## Formats

| `format=` | Output | Notes |
|---|---|---|
| `csv` | `.csv` | Streaming; arrays JSON-encoded per cell |
| `json` | `.json` | Streaming; nested arrays preserved natively |
| `xlsx` | `.xlsx` | Streaming via OpenSpout; constant memory at any row count |
| `xml` | `.xml` | Streaming; requires `format:root_name` and `format:branch_name` |

---

## Outputs (Destinations)

| `output=` | Effect | Required params |
|---|---|---|
| `download` | Streams file to browser as a download | `output:filename` |
| `local` | Saves file to a server path | `output:filename`, `output:path` |

---

## Modifiers

Modifiers post-process individual column values. Chain them with `|`:

```ee
{exp:export:entries
    channel="blog"
    format="csv"
    output="download"
    output:filename="blog.csv"
    modify:entry_date="ee_date[%Y-%m-%d]"
    modify:title="uc_words"
    modify:body="replace_with[REDACTED]"
}
```

| Modifier | Effect |
|---|---|
| `ee_date[format]` | Format a Unix timestamp using EE's localisation engine |
| `ee_decrypt` | Decrypt an EE-encrypted value |
| `replace_with[value]` | Replace the entire column value with a literal string |
| `uc_first` | Uppercase the first character |
| `uc_words` | Uppercase the first letter of each word |

---

## Param Namespacing

Parameters are routed to the correct pipeline layer by prefix:

| Prefix | Consumed by |
|---|---|
| *(none)* | Global / shared |
| `source:` | The active Source |
| `format:` | The active Format |
| `output:` | The active Output |
| `modify:field_name=` | Modifier declarations |
| `search:field_name=` | Field-level search filters (Members, SQL) |

---

## Excluding Columns

The `exclude` parameter accepts a pipe-separated list of column names to remove from every output row. All other columns are included automatically.

```ee
{exp:export:members
    exclude="password|salt|private_notes"
    format="csv"
    output="download"
    output:filename="members-safe.csv"
}
```

---

## Extending

Export is built around a factory + strategy pattern. Every layer is a named plugin resolved at runtime, and any installed EE addon can register its own implementations via `addon.setup.php` — no changes to Export's source code required.

```php
// your_addon/addon.setup.php
'export' => [
    'sources'   => ['orders'    => \YourAddon\Export\Sources\Orders::class],
    'formats'   => ['tsv'       => \YourAddon\Export\Formats\Tsv::class],
    'outputs'   => ['s3'        => \YourAddon\Export\Output\S3::class],
    'modifiers' => ['truncate'  => \YourAddon\Export\Modifiers\Truncate::class],
    'fields'    => ['bloqs'     => \YourAddon\Export\Fields\Bloqs::class],
],
```

Once declared, your plugin is available in any template tag:

```ee
{exp:export:orders
    status="complete"
    format="tsv"
    output="s3"
    output:bucket="my-exports"
    output:filename="orders.tsv"
    modify:total="my_currency_format"
}
```

For complete contracts, base classes, streaming interfaces, and worked examples for all five layers, see **[EXTENDING.md](EXTENDING.md)**.

---

## Field Handlers

Export ships with handlers for EE's first-party complex field types:

| Field type | Output |
|---|---|
| `date` | Unix timestamp integer |
| `file` | Absolute URL string |
| `relationship` | `[{"entry_id": 5, "title": "My Entry"}]` |
| `grid` | Array of row objects |
| `fluid_field` | Array of typed instances |

Third-party field types (e.g. Bloqs, Coilpack fields) can be supported by registering a handler in your addon's `addon.setup.php` as shown above. Handlers extend `AbstractField` and receive the raw stored value, field metadata, and a pre-fetched context bag of batch-loaded relationship and grid data.

---

## Streaming Architecture

The Entries and Grid sources process data in configurable chunks (`chunk_size`, default 500 rows) and all four formats support streaming writes — meaning memory consumption stays constant regardless of how many rows are exported. A 1,000,000-row XLSX export uses the same peak memory as a 100-row one.

Non-streaming sources (Members, SQL) load all rows into memory before writing; these are suitable for datasets up to ~50k rows on typical shared hosting.

---

## Full Documentation

| Document | Contents |
|---|---|
| **[DOCUMENTATION.md](DOCUMENTATION.md)** | Complete tag reference — all parameters, output shapes, format options, modifier syntax, and worked examples for every built-in tag |
| **[EXTENDING.md](EXTENDING.md)** | Developer guide — base class contracts, streaming interfaces, validation API, and end-to-end examples for all five extension layers |
