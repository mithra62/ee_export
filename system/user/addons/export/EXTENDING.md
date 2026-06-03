# Extending Export

This document explains how to add custom Sources, Formats, Modifiers, and Output destinations to the Export addon.

---

## How the Plugin System Works

Every export runs through a five-stage pipeline:

```
Template tag params
    → AbstractTag.params()       collect and namespace params
    → ExportService.validate()   validate each plugin
    → ExportService.build()      run the pipeline:
        SourcesService  → Source.compile()       fetch data
        ModifiersService → Modifier.process()    transform fields
        FormatsService   → Format.compile()      write file
        OutputService    → Destination.process() deliver file
```

### Param namespacing

Each layer of the pipeline reads only its own params. In a template tag, prefix params with the layer name:

```
{exp:export:members
    format="csv"              ← global
    source:limit="50"         ← read by Source
    format:separator=";"      ← read by Format
    output:filename="out.csv" ← read by Destination
    modify:email="uc_first"   ← read by Modifiers
    output="download"         ← global
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
$this->getOption('key');           // returns null if not set
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
protected function getValidator(): Validator
{
    $validator = parent::getValidator();
    $validator->defineRule('dirExists', function ($key, $value) {
        return is_dir($value) ? true : 'directory does not exist';
    });
    return $validator;
}
```

---

## 1. Creating and Using Source Objects

Sources fetch data and return it as a flat 2-D array for the rest of the pipeline.

**Base class:** `Mithra62\Export\Plugins\AbstractSource`  
**Namespace:** `Mithra62\Export\Sources\`  
**Tag param:** `source="your_name"` (usually set by the tag class, not the template author)

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
| `$this->cleanFields(array $row)` | Filter/reorder columns per the `fields=` tag param |

### Example — `Orders` source

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

### Wiring a tag class

Built-in tags (`Members`, `Query`, etc.) hard-code their source name. For a custom source, add a tag class:

```php
<?php
namespace Mithra62\Export\Tags;

class Orders extends AbstractTag
{
    public function process()
    {
        $params = $this->params();
        $params['source']         = 'orders';
        $params['source:status']  = $this->param('status');
        $params['source:limit']   = $this->param('limit');
        $this->compile($params);
    }
}
```

Template usage:

```
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

Formats receive the compiled source data, write a file, and return the path.

**Base class:** `Mithra62\Export\Plugins\AbstractFormat`  
**Namespace:** `Mithra62\Export\Formats\`  
**Tag param:** `format="your_name"`

### Contract

```php
public function compile(AbstractSource $source): string
```

Write the export to a file and return its absolute path.

### Inherited helpers

| Method | Purpose |
|---|---|
| `$source->getExportData()` | 2-D array of rows keyed by column name |
| `$this->getCacheDirPath()` | Writable temp directory (trailing slash included) |
| `$this->getCacheFilename()` | Unique random filename with no extension |
| `$this->writeContent($content, $path)` | Write a string to a file |
| `$this->getOption('key', $default)` | Read a `format:` param |

### Built-in formats

| Tag value | Class | Required params | Notes |
|---|---|---|---|
| `csv` | `Csv` | — | `format:separator`, `format:enclosure`, `format:escape`, `format:newline` all optional |
| `json` | `Json` | — | Plain `json_encode` |
| `xlsx` | `Xlsx` | — | `format:bold_cols="y"` to bold header row |
| `xml` | `Xml` | `root_name`, `branch_name` | Element names for root and each record |

### Example — `Tsv` (tab-separated) format

```php
<?php
namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;

class Tsv extends AbstractFormat
{
    public function compile(AbstractSource $source): string
    {
        $rows = $source->getExportData();
        $path = $this->getCacheDirPath() . $this->getCacheFilename() . '.tsv';

        $fp = fopen($path, 'w');
        fputcsv($fp, array_keys($rows[0]), "\t");
        foreach ($rows as $row) {
            fputcsv($fp, $row, "\t");
        }
        fclose($fp);

        return $path;
    }
}
```

Template usage:

```
{exp:export:members
    format="tsv"
    output="download"
    output:filename="members.tsv"
}
```

---

## 3. Creating and Using Modifier Objects

Modifiers transform individual field values. They run after the source compiles and before the format writes the file. Multiple modifiers can be chained per field using `|`.

**Base class:** `Mithra62\Export\Plugins\AbstractModifier`  
**Namespace:** `Mithra62\Export\Modifiers\`  
**Tag param:** `modify:field_name="modifier_name[param1][param2]"`

### Contract

```php
public function process(mixed $value): mixed
```

Receive a value, return the transformed value.

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
$this->getParam('search');           // returns '' if not set
$this->getParam('search', 'default'); // returns default if not set
```

### Chaining

```
modify:email="ee_decrypt|uc_first"
```

Modifiers run left to right. The output of each becomes the input of the next.

### Built-in modifiers

| Tag syntax | Class | Params | Effect |
|---|---|---|---|
| `ee_date[format]` | `EeDate` | `format` | Format Unix timestamp via `ee()->localize->format_date()` |
| `ee_decrypt` | `EeDecrypt` | — | Decrypt via `ee('Encrypt')->decrypt()` |
| `replace_with[value]` | `ReplaceWith` | `with` | Replace the entire value with a literal string |
| `uc_first` | `UcFirst` | — | `ucfirst()` |
| `uc_words` | `UcWords` | — | `ucwords()` |

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

```
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

`$finished_export` is the absolute path to the temp file. Return truthy on success; the temp file is deleted by `ExportService` after `process()` returns.

### Inherited helpers

| Item | Purpose |
|---|---|
| `$this->getOption('key', $default)` | Read an `output:` param |
| `protected bool $force_exit = false` | Set to `true` to call `exit` after delivery (required for browser downloads) |
| `$this->shouldDie()` | Read by `ExportService`; triggers exit if `true` |

### Built-in destinations

| Tag value | Class | Required params | Behaviour |
|---|---|---|---|
| `download` | `Download` | `filename` | Streams file to browser via `readfile()`; exits after |
| `local` | `Local` | `filename`, `path` | Copies file to a local directory |

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
        $prefix   = ltrim($this->getOption('prefix', ''), '/');

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

```
{exp:export:members
    format="csv"
    output="s3"
    output:bucket="my-exports-bucket"
    output:prefix="members/"
    output:filename="members-export.csv"
}
```

---

## 5. Registering Custom Plugins

No registration is required. The factory services resolve classes purely by name and namespace. Place your class in the correct namespace and ensure it is autoloaded.

**Option A — add to this addon's source tree.** Drop the file in the matching directory (`Sources/`, `Formats/`, etc.) and Composer's autoloader picks it up.

**Option B — distribute in a separate addon.** Register the `Mithra62\Export\` namespace in your addon's autoloader pointing at your files, or add a `psr-4` entry to this addon's `composer.json`.

### Custom tag method registration

EE routes `{exp:export:foo}` to a `foo()` method on the module class, or to a tag class at `Tags\Foo`. Add your tag class to `Tags/` following the same pattern as the built-in ones, and EE will pick it up automatically via the addon's tag routing.
