# ExpressionEngine Export 

Export allows for easy and customizable exports of data from within your ExpressionEngine installation. Export contains template tags you use to configure your export specific to your and your clients needs. 

> Note this is in open alpha at the moment and should NOT be used in production. Use at your own risk. 

## Features

1. Export to Excel, XML, JSON, and CSV
2. Channel Entries, Members, and more, all through template tags
3. Save exports to location or download through a browser
4. Apply "modifiers" to exported data to transform it before save

### Basic Example

The below will force download of the export in JSON format, with a data from the SQL, named `members.json`

```html
{exp:export:query
    sql="SELECT * FROM exp_members"
    format="json"
    output="download"
    output:filename="members.json"
}
```

## Template Tags

Exporting data is all done through template tags and parameters. This allow for granular and ah-hoc control over your data exporting and allows for fast implementation. 

### Universal Parameters

The below parameters can be used on all template tags

| Command | Description | Default |
| :--- | :---: | :---: |
| `format` | The desiered export format | None |
| `output` | How you want the export delivered | None |
| `fields` | A pipe delimited list of fields you want inccluded in your export | None |

### `query`

Execute an Export of a database table using a custom query

#### Params

These params are unique to the `query` template tag

| Command | Description | Default |
| :--- | :---: | :---: |
| `sql` | The SQL query to execute | None |

#### Basic Example

```html
{exp:export:query
    sql="SELECT * FROM exp_members"
    format="json"
    output="download"
    output:filename="members.json"
}
```

#### Advanced Example

```html
{exp:export:query
    sql="SELECT * FROM exp_members LIMIT 20"
    modify:last_activity="ee_date[%y-%m-%d]"
    modify:join_date="ee_date[%y-%m-%d]"
    modify:last_visit="ee_date[%y-%m-%d]"
    modify:last_entry_date="ee_date[%y-%m-%d]"
    modify:last_comment_date="ee_date[%y-%m-%d]"
    modify:language="uc_words"
    modify:password="replace_with[*******]"
    modify:unique_id="replace_with[*******]"
    modify:crypt_key="replace_with[*******]"
    format="xlsx"
    format:bold_cols="y"
    format:ucfirst_cols="y"
    output="local"
    output:filename="members.xlsx"
    output:path="/path/to/location"
}
```

### `members`

Export ExpressionEngine members 

#### Params

These params are unique to the `members` template tag

| Command | Description | Default |
| :--- | :---: | :---: |
| `roles` | A pip delimited collection of role_id values | None |
| `join_start` | A `strtotime` compatible date | None |
| `join_end` | A `strtotime` compatible date | None |
| `last_login_start` | A `strtotime` compatible date | None |
| `last_login_join_end` | A `strtotime` compatible date | None |
| `search:field_name` | Allows for strict searching of members based on value matching | None |

#### Basic Example

```html
{exp:export:members
    format="json"
    output="download"
    output:filename="members.json"
}
```

## Modifiers

Modifiers allow exports to have it's data overriden through parameters. At the moment, Export only ships with a few, but that number will grow over time. You can chain multiple modifiers togethr using the pipe `|` string. 

To use modifiers, you set parameters and declare them on a per key value. For example, with a `member` export, you can replace all passwords with `*******` through a parameter of `modify:password="replace_with[*******]"`. 

Parameters for modifiers are called using brackets for each. 

#### Example

```html
modify:join_date="ee_date[%y-%m-%d]|"
```

### `ee_date`

Will take a Unix timestamp and format it as described. 

#### Example

```html
{exp:export:members
    format="json"
    output="download"
    modify:join_date="ee_date[%y-%m-%d]"
    output:filename="members.json"
}
```

### `uc_words`

Runs the value through the internal PHP function

#### Example

```html
{exp:export:members
    format="json"
    output="download"
    modify:first_name="uc_first"
    output:filename="members.json"
}
```

### `replace_with`

Will replace any output with the value provided. 

#### Example

```html
{exp:export:members
    format="json"
    output="download"
    modify:password="replace_with[*******]"
    output:filename="members.json"
}
```

### `uc_words`

Runs the value through the internal PHP function

#### Example

```html
{exp:export:members
    format="json"
    output="download"
    modify:city="uc_words"
    output:filename="members.json"
}
```

## Formats

Export comes equipped with multiple formats for your exports. Note that all format based parameters should be prefixed with `format:`.

### XML

Generate an XML document 

#### Params

These params are unique to xml documents

| Command | Description | Default |
| :--- | :---: | :---: |
| `format:root_name` | The name to use to contain your XML nodess | None |
| `format:branch_name` | What the containing node should be called | None |

#### Basic Example

```html
{exp:export:query
    sql="SELECT * FROM exp_members"
    format="xml"
    format:root_name="members_table"
    format:branch_name="members"
    output="download"
    output:filename="members.xml"
}
```

### JSON

Will convert an Export into JSON

#### Params

None

#### Basic Example

```html
{exp:export:query
    sql="SELECT * FROM exp_members"
    format="json"
    output="download"
    output:filename="members.json"
}
```

### CSV

Export data in a comma seperated value format

#### Params

These params are unique to `csv` documents

| Command | Description | Default |
| :--- | :---: | :---: |
| `format:separator` | Sets the field delimiter (one character only). | `,` |
| `format:enclosure` | The field enclosure (one character only). | `"` |
| `format:escape` | Tthe escape character (one character only). | `\\` |
| `format:newline` | How new lines are created | `\n` |

#### Basic Example

```html
{exp:export:query
    sql="SELECT * FROM exp_members"
    format="csv"
    output="download"
    output:filename="members.csv"
}
```

### Xlsx

Export data in Excel format 

#### Params

These params are unique to `xlsx` documents

| Command | Description | Default |
| :--- | :---: | :---: |
| `bold_cols` | Whether the columns within the spreadsheet should be bolded | None |

## Destinations

Destinations are how delivery is defined. At this point, there are 2: `download` and `local`

### Download

Will force the export file to download

#### Params

These params are unique to `download` destinations

| Command | Description | Default |
| :--- | :---: | :---: |
| `output:filename` | The name for the file upon export | None |

#### Basic Example

```html
{exp:export:query
    sql="SELECT * FROM exp_members"
    format="csv"
    output="download"
    output:filename="members.csv"
}
```

### Local

Places the Export at the specified location 

#### Params

These params are unique to `download` destinations

| Command | Description | Default |
| :--- | :---: | :---: |
| `output:filename` | The name for the file upon export | None |
| `output:path` | The full system path to the directory the export will be stored | None |

#### Basic Example

```html
{exp:export:query
    sql="SELECT * FROM exp_members"
    format="csv"
    output="local"
    output:filename="members.csv"
    output:path="/path/to/dir"
}
```