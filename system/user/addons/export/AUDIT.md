# Export Addon — Pre-1.0.0 Release Audit

**Audit date:** 2026-06-09  
**Audited version:** 1.0.0-beta.1  
**Status:** Open — items below are unresolved unless marked ✅

---

## How to use this document

Each issue has an ID (`C-1`, `H-2`, `M-5`, etc.). When an issue is resolved, mark it ✅ and add a brief note. Severity levels:

- **Critical** — security vulnerability or data-loss risk; must fix before any public release
- **High** — incorrect behaviour that silently produces wrong output or exposes a security surface; fix before 1.0.0
- **Medium** — incorrect behaviour in specific scenarios, missing validation, or code quality issues that affect reliability; fix before 1.0.0
- **Low** — polish, dead code, documentation gaps, deprecation warnings; fix before 1.0.0 or document as known limitation

---

## Critical

### C-1 — SQL template tag has no Super Admin gate

- **File:** `Tags/Query.php::process()`
- **Status:** Open
- **Description:** The `{exp:export:query sql="..."}` template tag has no permission check. Any EE member with template-editing access can execute arbitrary SELECT queries against the database — including queries that enumerate PII, password hashes, or session tokens from any table. The Super Admin restriction added in beta.1 only covers the CP form save path; the tag itself is completely open.
- **Fix:** Add `isSuperAdmin()` check at the top of `Tags/Query.php::process()` before calling `compile()`. Mirror the pattern used in the CP:
  ```php
  if (! ee('Permission')->isSuperAdmin()) {
      show_error(lang('export_err_sql_superadmin_only'), 403);
  }
  ```
- **Notes:**

---

### C-2 — Temp files leaked on exception during streaming ✅

- **File:** `Services/ExportService.php::buildStreaming()`, `Formats/Csv.php`, `Formats/Json.php`, `Formats/Xlsx.php`
- **Status:** Resolved
- **Description:** `openFile()` creates a temp file in `PATH_CACHE/export/` at the start of a streaming export. If `source->nextChunk()` or `format->writeChunk()` throws mid-stream (DB error, disk full, timeout, etc.), execution leaves `buildStreaming()` without reaching the `deliver()` call. The `unlink()` inside `deliver()` is never reached. Temp files accumulate in the cache directory indefinitely and may contain PII (member data, entry content, etc.).
- **Fix:** Wrap `buildStreaming()` in try/finally:
  ```php
  $path = null;
  try {
      // ... streaming loop ...
      $path = $format->finalizeFile();
  } finally {
      if ($path && file_exists($path)) {
          unlink($path);
      }
  }
  ```
  Alternatively, track the temp path in the `Run` route's catch block and unlink there.
- **Notes:**

---

## High

### H-1 — XLSX bold header never applied despite the CP toggle

- **File:** `Formats/Xlsx.php` line 33
- **Status:** Open
- **Description:** The bold-header style check is `$this->getOption('bold_cols') === true`. The value stored by `postToSettings()` is the string `'y'` (from the EE toggle field), not the boolean `true`. The strict comparison always fails — the header row is never bolded regardless of the CP setting. This is a silent data formatting bug; users will toggle the option and see no effect.
- **Fix:**
  ```php
  $bold = in_array($this->getOption('bold_cols'), [true, 'y', '1', 1], true);
  $style = $bold ? (new Style())->setFontBold() : new Style();
  ```
- **Notes:**

---

### H-2 — `Output/Local.php` path traversal via filename option ✅

- **File:** `Output/Local.php::process()` line ~24
- **Status:** Resolved — `basename()` applied to filename before path construction
- **Description:** The destination path is constructed as `rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $this->getOption('filename')`. The `dirExists`/`dirWritable` validators only check the *directory* component. The filename itself is never sanitised. A stored filename of `../../config/database.php` would write the export file two directories above the approved path, potentially overwriting arbitrary server files.
- **Fix:** Apply `basename()` to strip any directory components from the filename before building the path:
  ```php
  $filename = basename($this->getOption('filename'));
  $dest     = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $filename;
  ```
- **Notes:**

---

### H-3 — `relationship_fields` param injected raw into SELECT clause

- **File:** `Services/EntryService.php` lines 597–599
- **Status:** Open
- **Description:** Column names from the `relationship_fields` tag param (or stored CP setting) are interpolated directly into the SQL SELECT clause:
  ```php
  $select .= ', cd.' . implode(', cd.', $extra);
  ```
  A value like `password FROM exp_members --` injects arbitrary SQL into the query. The param is user-supplied (template author or CP user) and receives no sanitisation.
- **Fix:** Allowlist each field name to alphanumeric + underscore before use:
  ```php
  $extra = array_filter($extra, fn($f) => preg_match('/^[a-z0-9_]+$/i', $f));
  ```
- **Notes:**

---

### H-4 — `MemberService::getColumns()` hardcodes `exp_` table prefix ✅

- **File:** `Services/MemberService.php` line ~43
- **Status:** Resolved — replaced `exp_members` with `ee()->db->dbprefix . 'members'`
- **Description:** `ee()->db->query("SHOW COLUMNS FROM exp_members")` hardcodes the `exp_` prefix. EE installations configured with a non-default table prefix (common on shared hosting or multi-site setups) will get an empty result. The Members export then produces rows with all core columns silently missing — no error, no warning.
- **Fix:** Use the configured prefix:
  ```php
  ee()->db->query("SHOW COLUMNS FROM " . ee()->db->dbprefix . "members")
  ```
  Or use CI's built-in: `ee()->db->list_fields('members')`.
- **Notes:**

---

### H-5 — `Entries::nextChunk()` fetches only 8 of the documented columns ✅

- **File:** `Sources/Entries.php` line ~103
- **Status:** Resolved — SELECT expanded to all 24 core columns matching `columnsForEntries()`; `buildRow()` now spreads `$entry` directly so SELECT and row output stay permanently in sync
- **Description:** The SELECT in `nextChunk()` hardcodes `entry_id, title, url_title, status, entry_date, expiration_date, author_id, edit_date`. The CP column picker (via `CpService::columnsForEntries()`) also offers `sticky`, `comment_total`, `channel_id`, `ip_address`, `view_count_one`–`four`, `allow_comments`, and others. Users who configure a whitelist or see these columns listed in the CP will get empty values for all of them silently.
- **Fix:** Expand the SELECT to cover all columns exposed by `columnsForEntries()`, or use `channel_titles.*` and let `cleanFields()` handle the whitelist/blacklist filtering.
- **Notes:**

---

## Medium

### M-1 — CSRF verification on CP POST routes not confirmed

- **File:** `ControlPanel/Routes/Create.php::handlePost()`, `Edit.php::handlePost()`, `Delete.php::process()`
- **Status:** ✅ Resolved
- **Description:** The routes rely on EE's base `Mcp` class routing. It is not confirmed whether `Mcp` automatically verifies CSRF tokens on POST or whether that is delegated to the form rendering layer. If CSRF is not automatically enforced, Create/Edit/Delete are all vulnerable to cross-site request forgery — an attacker can cause an authenticated admin to create, modify, or delete export configurations.
- **Fix:** Verified — EE's `Core::process_secure_forms()` runs on every CP request before any route handler fires, enforcing CSRF token validation globally. No per-route code is needed.
- **Notes:**

---

### M-2 — `XmlService::addXmlNodes()` produces invalid XML for numeric array keys

- **File:** `Services/XmlService.php` lines ~220–229
- **Status:** ✅ Resolved
- **Description:** When a field value is an array with numeric keys (Grid rows, Fluid instances, Relationship arrays all return numeric-keyed arrays), the code iterates and calls `$this->addNode($_key, $sub)` where `$_key` is an integer. `XMLWriter::startElement()` given a numeric string produces invalid element names (`<0>`, `<1>`, etc.) or throws. This will corrupt or crash XML exports for any entry with complex field types.
- **Fix:** When the key is numeric, use a generic element name with an index attribute:
  ```php
  $element = is_int($_key) ? 'item' : $_key;
  // add index="N" attribute if numeric
  ```
  Or JSON-encode arrays as a single text node for XML output (matching the CSV/XLSX flat behaviour).
- **Notes:**

---

### M-3 — `last_login_start`/`last_login_end` compare date strings against a Unix int column ✅

- **File:** `Sources/Members.php` lines ~170–173
- **Status:** Resolved — `strtotime()` applied to both values, matching the existing `join_start`/`join_end` pattern
- **Description:** `join_start` and `join_end` correctly call `strtotime()` before being used in `WHERE` clauses against the `join_date` INT column. `last_login_start` and `last_login_end` pass the raw string directly — MySQL implicitly casts a date string like `'2024-01-01'` to `2024` (the year component as an integer) and produces completely wrong filter results with no error.
- **Fix:**
  ```php
  if ($this->getOption('last_login_start') && $this->getOption('last_login_end')) {
      $query->where('members.last_visit >=', strtotime($this->getOption('last_login_start')));
      $query->where('members.last_visit <=', strtotime($this->getOption('last_login_end')));
  }
  ```
- **Notes:**

---

### M-4 — Dead code with hardcoded project-specific field/column IDs

- **File:** `Services/EntryService.php` lines ~205–221
- **Status:** ✅ Resolved
- **Description:** `getNotifications()` calls `getGridData(215, $entry_id, ['copy' => 'col_id_116', 'type' => 'col_id_115'])` — hardcoded grid field ID `215` and column IDs `116`/`115` from a specific development site. Several other methods appear unused in the export pipeline: `filterCategories()`, `filterString()`, `getEntryCats()`, `getEntriesCatIds()`, `getCatId()`, `createGridData()`, `updateGridData()`, `getGridFluidFieldId()`. Dead code with site-specific IDs will cause immediate confusion or errors for any user on a different installation.
- **Fix:** Removed all nine dead methods from `Services/EntryService.php`. Grep confirmed zero call sites outside the definitions themselves.
- **Notes:**

---

### M-5 — `AbstractPlugin::toArray()` dynamic property access breaks on PHP 8.2+

- **File:** `Plugins/AbstractPlugin.php` lines ~67–74
- **Status:** ✅ Resolved
- **Description:**
  ```php
  foreach ($this->options as $key => $value) {
      $data[$key] = $this->{$key};
  }
  ```
  Dynamic property access (`$this->{$key}`) for arbitrary option names (e.g., `'channel'`, `'status'`, `'sql'`) that are not declared class properties triggers deprecation warnings in PHP 8.1 and will become fatal errors in PHP 9. EE 7 targets PHP 8.0+ and will move forward. The method also appears unused in the current pipeline.
- **Fix:** Either rewrite as `return $this->options;` or remove the method if it is truly unused.
- **Notes:**

---

### M-6 — CP `Run` route skips `ExportService::validate()`

- **File:** `ControlPanel/Routes/Run.php` lines ~43–55
- **Status:** ✅ Resolved
- **Description:** The template tag path calls `$export->validate()` before `$export->build()`. The CP Run route calls `$export->setParameters($params)->build()` directly, skipping validation entirely. A corrupted or manually-edited settings JSON blob will throw an unhandled exception with a raw PHP stack trace instead of a clean CP error message.
- **Fix:** Call `validate()` before `build()` in `Run::process()`, matching the tag execution path. Catch the resulting validation errors and show them as a CP alert.
- **Notes:**

---

### M-7 — `Entries` `entry_id` field shows "Pipe-separated" hint but only accepts a single int

- **File:** `Sources/Entries.php::nextChunk()`, `Sources/Grid.php::nextChunk()`, `Sources/Fluid.php::nextChunk()`
- **Status:** ✅ Resolved
- **Description:** `nextChunk()` applies `where('entry_id', (int) $this->getOption('entry_id'))` — the cast to `int` means only a single ID is ever used. The CP form renders `src_entries_entry_id` with a "Pipe-separated" description hint, implying multi-ID support that doesn't exist. Users will enter `1|2|3` and silently export only entry `1`. Entries source had no `entry_id` filter at all.
- **Fix:** Implemented `where_in` across all three sources. Pipe-separated values are parsed to an int array; single ID uses `where`, multiple use `where_in`, absent skips the clause entirely. Entries source also gained the missing filter.
- **Notes:**

---

### M-8 — `search:` params silently ignored for Entries, Grid, and Fluid sources

- **File:** `Tags/AbstractTag.php` line ~139`, `Sources/Members.php` line ~175`
- **Status:** Open
- **Description:** `AbstractTag::params()` parses `search:field_name` params for all tags and stores them. Only `Sources/Members.php` implements `applySearchFilters()`. For Entries, Grid, and Fluid sources the search params are parsed, stored, and then silently discarded. Template authors expecting `search:field_name="value"` to filter entries will get an unfiltered export with no indication that the param was ignored.
- **Fix:** Either implement search filtering for the remaining sources, or restrict `search:` parsing to `Tags/Members.php` only, and add a note to docs that `search:` is Members-only.
- **Notes:**

---

### M-9 — Grid/Fluid `limit` applies to entries, not output rows — undocumented ✅

- **File:** `Sources/Grid.php` line ~109, `Tags/Grid.php`, `Tags/Fluid.php`
- **Status:** Resolved — clarified in `Tags/Grid.php` and `Tags/Fluid.php` phpdocs; added `export_field_limit_grid_desc` / `export_field_limit_fluid_desc` lang strings with `setDesc()` wired in CpService; DOCUMENTATION.md §3.3 and §3.4 parameter tables updated inline
- **Description:** `$this->stream_offset` and `limit` count *entries* processed, not grid/fluid rows produced. A `limit="10"` export processes 10 entries' worth of rows, which may produce hundreds of output rows if each entry has many grid rows. This is correct behaviour, but it is not documented anywhere — template authors and CP users will expect `limit` to cap output rows.
- **Fix:** Add a clear note in the template tag phpdoc blocks and in the CP field description: "Limits the number of *entries* processed, not the number of output rows."
- **Notes:**

---

## Low

### L-1 — `upd.export.php::update()` is a stub with no upgrade migration path

- **File:** `upd.export.php`
- **Status:** Open
- **Description:** `update()` calls `parent::update()` only. There is currently nothing to upgrade from (1.0.0 is the first public release), so this is acceptable. However, future schema changes will have nowhere to go unless the migration pattern is established now.
- **Fix:** Add a comment documenting how future migrations should be added. Confirm that EE's `Installer::update()` auto-discovers versioned migration files.
- **Notes:** Acceptable for 1.0.0 release as long as migration pattern is documented.

---

### L-2 — `AbstractPlugin::getCacheContent()` reads a directory path, not a file path ✅

- **File:** `Plugins/AbstractPlugin.php` lines ~170–177
- **Status:** Resolved — method confirmed unused (grep found zero call sites) and removed
- **Description:** `getCacheContent()` calls `file_get_contents($this->getCachePath())` where `getCachePath()` returns the cache *directory* path. Reading a directory returns `false`; the guard silently returns an empty array. The method appears unused in the current streaming pipeline.
- **Fix:** Remove the method if unused, or fix to `$this->getCacheDirPath() . $this->getCacheFilename()`.
- **Notes:**

---

### L-3 — `openFile()` does not check for `fopen()` failure ✅

- **File:** `Formats/Csv.php` line ~25, `Formats/Json.php` line ~25`, `Formats/Xlsx.php` line ~30`
- **Status:** Resolved — `\RuntimeException` thrown on `false` return from `fopen()` in Csv and Json; Xlsx uses OpenSpout's `openToFile()` which already throws `IOException` on failure so no change needed there
- **Description:** `fopen($this->stream_path, 'w')` returns `false` when the cache directory is unwritable or the path is invalid. No check is made — subsequent `fputcsv()`/`fwrite()` calls on a `false` handle produce PHP warnings and corrupted output with no meaningful error message.
- **Fix:** Check the return value and throw a descriptive exception:
  ```php
  if ($this->fp === false) {
      throw new \RuntimeException("Export: could not open cache file at {$this->stream_path}");
  }
  ```
- **Notes:**

---

### L-4 — `Tags/Grid.php` phpdoc describes `fields=` as an exclusion list ✅

- **File:** `Tags/Grid.php` line ~28
- **Status:** Resolved — fixed during M-9 pass; `fields=` now correctly described as whitelist, `exclude=` added as a separate entry
- **Description:** The docblock says `fields="col1|col2"` is "Columns to exclude from output (exclusion list)". It is the *inclusion* whitelist. `exclude=` is the exclusion list. Any developer reading the source to understand the tag params will get the wrong behaviour.
- **Fix:** Correct the phpdoc to match `AbstractSource::cleanFields()` semantics.
- **Notes:**

---

### L-5 — Cache directory created world-writable (`0777`)

- **File:** `Plugins/AbstractPlugin.php` line ~93
- **Status:** ✅ Resolved
- **Description:** `mkdir($cache_path, 0777, true)` makes the temp export cache directory world-writable. On shared hosting any process can read or modify files in `PATH_CACHE/export/` — including temp files containing PII (member data, entries, etc.) before they are unlinked.
- **Fix:** Use `0750` (owner full, group read+execute, world none). Consider also writing an `index.html` deny-all file into the directory on creation.
- **Notes:**

---

### L-6 — `ExportService` setters missing nullable type declaration ✅

- **File:** `Services/ExportService.php` lines ~68, ~92, ~116, ~140
- **Status:** Resolved — all four setters updated from `Type $param = null` to `?Type $param = null`
- **Description:** All four service setters are declared `function setOutput(OutputService $output = null)` — the parameter is not typed as `?OutputService`. This pattern is deprecated in PHP 8.x and will become a type error in strict mode. EE 7 targets PHP 8.0+.
- **Fix:** Change all four signatures to use nullable types:
  ```php
  public function setOutput(?OutputService $output = null): static
  ```
- **Notes:**

---

### L-7 — `AbstractTag::compile()` catches `NoDataException` silently; no `{if no_results}` support ✅

- **File:** `Tags/AbstractTag.php`
- **Status:** Resolved — bare `return` replaced with `ee()->TMPL->no_results()` before return; DOCUMENTATION.md §3 updated with `{if no_results}` usage example
- **Description:** When no data is found, `NoDataException` is caught and the tag returns empty. This is correct for download tags (no file = nothing to deliver). However, there is no way for a template to distinguish "export ran successfully and found nothing" from "export failed to configure correctly." EE's `no_results` pattern (`ee()->TMPL->no_results()`) is not hooked in.
- **Fix:** For non-download outputs (`local`), consider calling `ee()->TMPL->no_results()` in the catch block. For download outputs, document explicitly that empty results produce no output.
- **Notes:** Acceptable for 1.0.0 with documentation; full `no_results` support could be a 1.1 feature.

---

### L-8 — Trailing empty string key in language file ✅

- **File:** `language/english/export_lang.php` line 147
- **Status:** Resolved — `'' => ''` entry removed
- **Description:** `'' => ''` at the end of the `$lang` array is a leftover from a copy-paste template. Harmless but untidy.
- **Fix:** Remove the line.
- **Notes:**

---

### L-9 — Shared `source:channel` / `source:field` storage key collides across Grid and Fluid configs

- **File:** `Services/CpService.php` lines ~272, ~321–322
- **Status:** ✅ Resolved
- **Description:** Both `src_grid_channel` and `src_fluid_channel` POST keys map to the same stored key `source:channel`. When the Edit form renders, it uses `$channel_id` from `source:channel` to pre-fill both the Grid and Fluid channel selects. This means if a user edits a Grid config that uses channel 3 and then switches the Source select to Fluid, the Fluid channel select will already show channel 3 — which may or may not be correct, and could cause confusing pre-filled states.
- **Fix:** Store with source-specific keys (`grid:channel`, `fluid:channel`) and adjust `buildParamsFromSettings()` to re-map them to `source:channel` when building params for the export pipeline.
- **Notes:** Low impact in practice; users saving from the correct source see correct pre-fill.

---

## Resolution summary

| ID | Severity | Area | Status |
|----|----------|------|--------|
| C-1 | Critical | Security — SQL tag permission gate | Open |
| C-2 | Critical | Resource leak — streaming temp files | ✅ Resolved |
| H-1 | High | Bug — XLSX bold header | Open |
| H-2 | High | Security — Local output path traversal | ✅ Resolved |
| H-3 | High | Security — SQL injection via relationship_fields | Open |
| H-4 | High | Bug — MemberService hardcoded table prefix | ✅ Resolved |
| H-5 | High | Bug — Entries missing documented columns | ✅ Resolved |
| M-1 | Medium | Security — CSRF verification on CP routes | ✅ Resolved |
| M-2 | Medium | Bug — XML invalid element names for numeric keys | ✅ Resolved |
| M-3 | Medium | Bug — last_login filters pass string to int column | ✅ Resolved |
| M-4 | Medium | Code quality — dead code with hardcoded field IDs | ✅ Resolved |
| M-5 | Medium | PHP 8.2 — dynamic property access in toArray() | ✅ Resolved |
| M-6 | Medium | Bug — Run route skips ExportService::validate() | ✅ Resolved |
| M-7 | Medium | UX — Entries entry_id pipe hint vs int cast | ✅ Resolved |
| M-8 | Medium | Feature gap — search: param ignored for non-Members | Open |
| M-9 | Medium | Docs — Grid/Fluid limit applies to entries not rows | ✅ Resolved |
| L-1 | Low | Upgrade — update() stub needs comment | Open |
| L-2 | Low | Dead code — getCacheContent() reads directory | ✅ Resolved |
| L-3 | Low | Error handling — fopen() failure not checked | ✅ Resolved |
| L-4 | Low | Docs — Grid phpdoc wrong for fields= param | ✅ Resolved |
| L-5 | Low | Security — cache dir created 0777 | ✅ Resolved |
| L-6 | Low | PHP 8 — nullable type declarations missing | ✅ Resolved |
| L-7 | Low | UX — no {if no_results} support | ✅ Resolved |
| L-8 | Low | Code quality — empty lang key | ✅ Resolved |
| L-9 | Low | UX — Grid/Fluid shared storage key in Edit form | ✅ Resolved |
