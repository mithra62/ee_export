# ExpressionEngine Export 

Export allows for easy and customizable exports of data from within your ExpressionEngine installation. Export contains template tags you use to configure your export specific to your and your clients needs. 

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

## Formats

Export comes equipped with multiple formats for your exports. Note that all format based parameters should be prefixed with `format:`.

### XML

Generate an XML document 

#### Params

These params are unique to XML documents

| Command | Description |
| :--- | :---: |
| `format:root_name` | The name to use to contain your XML nodess |
| `format:branch_name` | What the containing node should be called |

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

### Excel

## Sources

### Members

### Query

## Modifiers

## Destinations

### Download

### Local