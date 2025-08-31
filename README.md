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

#### Params

These params are unique to the `ee_date` modifier

| Command | Description | Default |
| :--- | :---: | :---: |
| `format:root_name` | The name to use to contain your XML nodess | None |


## Formats

Export comes equipped with multiple formats for your exports. Note that all format based parameters should be prefixed with `format:`.

### XML

Generate an XML document 

#### Params

These params are unique to XML documents

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

These params are unique to XML documents

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
    output="local"
    output:filename="members.csv"
    output:path="D:\Projects\mithra62\ee-product-dev\html\fdsa"
}
```

### Excel

## Sources

### Members

### Query

## Modifiers

## Destinations

### Download

### Local