# Export — Template Examples

Copy any of these files into your ExpressionEngine templates directory and adjust the parameters to match your site. Each file contains inline comments describing every parameter that needs to be changed.

---

## Directory structure

```
templates/
  entries/
    basic-csv-download.html          Basic channel entries → CSV browser download
    xlsx-save-to-server.html         Channel entries → XLSX saved to the server filesystem
    json-with-relationships.html     Channel entries → JSON with date formatting and relationship resolution
    exclude-sensitive-fields.html    Channel entries → CSV with specific columns removed
    whitelist-columns.html           Channel entries → CSV returning only named columns, in a set order
    no-results-conditional.html      Channel entries → CSV with {if no_results} handling

  members/
    basic-csv-download.html          All members → CSV browser download
    filter-by-roles.html             Members filtered by role IDs → XLSX download
    filter-by-date-range.html        Members filtered by join date window → JSON saved to server
    search-filters.html              Members filtered by field values (search: params) → CSV download
    exclude-sensitive-fields.html    Members → CSV with sensitive columns removed
    with-modifiers.html              Members → CSV with date formatting, title-casing, and redaction

  grid/
    basic-download.html              All Grid field rows across a channel → XLSX download
    single-entry.html                Grid rows for a single entry → JSON download
    with-relationships.html          Grid rows with relationship column resolution → CSV download

  fluid/
    basic-download.html              All Fluid field blocks across a channel → XLSX download
    single-entry.html                Fluid blocks for a single entry → JSON download
    specific-columns.html            Fluid blocks with a column whitelist → CSV download

  query/
    basic-csv-download.html          Raw SQL SELECT result → CSV browser download
```

---

## Quick-start

1. Pick the template closest to what you need.
2. Copy it into your EE templates directory (e.g. `templates/default_site/exports/`).
3. Update the parameters flagged in the inline comments — at minimum `channel`, `field`, and `output:filename`.
4. Link to the template from wherever you want to trigger the export (a button, a cron-hit URL, etc.).

For the full parameter reference see [DOCUMENTATION.md](../DOCUMENTATION.md).
