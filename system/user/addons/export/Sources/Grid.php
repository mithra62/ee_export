<?php

namespace Mithra62\Export\Sources;

use CI_DB_result;
use ExpressionEngine\Service\Validation\Validator;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;
use Mithra62\Export\Traits\SearchFilterTrait;

/**
 * Grid source — exports EE Grid field rows as a flat tabular dataset.
 *
 * Output shape (one row per grid row):
 *   entry_id | entry_title | row_order | <col_name_1> | <col_name_2> | ...
 *
 * Column values are processed through FieldsService using the same field-handler
 * pipeline as the Entries source.  The rel_data / rel_cache context arrays follow
 * the identical shape; the only difference is that the "entry_id" key used for
 * lookup is the grid row_id (each row has its own relationship value, so keying
 * by entry_id would collapse multiple rows into one).
 *
 * Native formats (JSON/XML) receive complex values as arrays; flat formats
 * (CSV/XLSX) serialise them via their flattenValue() helper.
 *
 * Streaming is supported — entries are processed in configurable chunks.
 */
class Grid extends AbstractSource
{
    use SearchFilterTrait;

    protected array $rules = [
        'source' => 'required',
        'channel' => 'required|validChannel',
        'field' => 'required|validGridField',
    ];

    // ── Streaming state ──────────────────────────────────────────────────────

    protected int $stream_offset = 0;
    protected int $stream_chunk_size = 500;
    protected int $stream_channel_id = 0;
    protected int $stream_field_id = 0;

    /** @var array<int, array>  column definitions keyed by col_id */
    protected array $grid_columns = [];

    /** @var int[]  col_ids whose col_type is 'relationship' */
    protected array $rel_col_ids = [];

    /** @var array<int, array>  channel field definitions keyed by field_id, used by SearchFilterTrait */
    protected array $channel_fields = [];

    // ── CP form fields ────────────────────────────────────────────────────────

    public function getCpFields(array $context = []): array
    {
        return [
            [
                'name' => 'channel', 'type' => 'select', 'label' => 'export_field_channel',
                'scoped' => true,
                'choices_callback' => fn($c) => $c['cp']->getChannelList(),
            ],
            [
                'name' => 'field', 'type' => 'select', 'label' => 'export_field_field',
                'scoped' => true,
                'choices_callback' => fn($c) => $c['cp']->getChannelFields((int) ($c['settings']['channel'] ?? 0), 'grid'),
            ],
            [
                'name' => 'status', 'type' => 'select', 'label' => 'export_field_status',
                'choices' => static::statusChoices(), 'default' => 'open',
            ],
            ['name' => 'author_id', 'type' => 'text', 'label' => 'export_field_author_id'],
            [
                'name' => 'entry_id', 'type' => 'text', 'label' => 'export_field_entry_id',
                'desc' => 'export_hint_pipe_sep',
            ],
            [
                'name' => 'limit', 'type' => 'text', 'label' => 'export_field_limit',
                'desc' => 'export_field_limit_grid_desc',
            ],
            ['name' => 'offset', 'type' => 'text', 'label' => 'export_field_offset', 'default' => '0'],
            ['name' => 'chunk_size', 'type' => 'text', 'label' => 'export_field_chunk_size', 'default' => '500'],
            [
                'name' => 'relationship_fields', 'type' => 'text',
                'label' => 'export_field_relationship_fields', 'desc' => 'export_hint_pipe_sep',
                'default' => 'title',
            ],
        ];
    }

    // ── AbstractSource contract ───────────────────────────────────────────────

    public function compile(): AbstractSource
    {
        $this->openStream();
        $rows = [];
        while (true) {
            $chunk = $this->nextChunk();
            if (empty($chunk)) {
                break;
            }
            foreach ($chunk as $row) {
                $rows[] = $row;
            }
        }
        $this->closeStream();

        if (empty($rows)) {
            throw new NoDataException("No grid rows found");
        }

        $this->setExportData($rows);
        return $this;
    }

    // ── Streaming interface ───────────────────────────────────────────────────

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function openStream(): void
    {
        $this->stream_offset = (int)$this->getOption('offset', 0);
        $this->stream_chunk_size = (int)$this->getOption('chunk_size', 500);

        $channel_id = ee('export:EntryService')
            ->getChannelId((string)$this->getOption('channel', ''));
        $this->stream_channel_id = $channel_id;

        $field_id = ee('export:EntryService')
            ->getGridFieldId((string)$this->getOption('field', ''), $channel_id);
        $this->stream_field_id = $field_id;

        $this->grid_columns = ee('export:EntryService')->getGridColumns($field_id);

        // Identify relationship columns so we can batch-resolve them
        foreach ($this->grid_columns as $col_id => $col_info) {
            if ($col_info['col_type'] === 'relationship') {
                $this->rel_col_ids[] = $col_id;
            }
        }

        // Loaded so SearchFilterTrait can resolve search:field_name to a channel
        // custom field on the parent entry (distinct from the grid's own columns).
        $this->channel_fields = ee('export:EntryService')->getChannelFields($channel_id);
    }

    public function nextChunk(): array
    {
        $channel_id = $this->stream_channel_id;
        $limit = $this->stream_chunk_size;

        // Respect hard limit (applies to entries, not grid rows)
        if ($this->getOption('limit')) {
            $hard_limit = (int)$this->getOption('limit') + (int)$this->getOption('offset', 0);
            $remaining = $hard_limit - $this->stream_offset;
            if ($remaining <= 0) {
                return [];
            }
            $limit = min($limit, $remaining);
        }

        // ── 1. Fetch entry page ───────────────────────────────────────────────
        $query = ee()->db
            ->select('entry_id, title, url_title, status, entry_date, author_id')
            ->from('channel_titles')
            ->where('channel_id', $channel_id)
            ->where('status', $this->getOption('status', 'open'));

        if ($this->getOption('author_id')) {
            $query->where('author_id', (int)$this->getOption('author_id'));
        }

        $entry_id_filter = array_filter(array_map('intval', explode('|', (string)$this->getOption('entry_id', ''))));
        if (count($entry_id_filter) === 1) {
            $query->where('entry_id', reset($entry_id_filter));
        } elseif (count($entry_id_filter) > 1) {
            $query->where_in('entry_id', $entry_id_filter);
        }

        $search = $this->getOption('search', []);
        if (!empty($search)) {
            $this->applySearchFilters($query, $search, $channel_id);
        }

        $result = $query->limit($limit, $this->stream_offset)->get();

        if (!($result instanceof CI_DB_result) || $result->num_rows() === 0) {
            return [];
        }

        $entry_rows = $result->result_array();
        $entry_ids = array_map('intval', array_column($entry_rows, 'entry_id'));

        // Index entries by ID for fast lookup during row building
        $entry_map = [];
        foreach ($entry_rows as $entry) {
            $entry_map[(int)$entry['entry_id']] = $entry;
        }

        // ── 2. Batch-load grid rows ───────────────────────────────────────────
        $all_grid_rows = ee('export:EntryService')
            ->batchGridData($this->stream_field_id, $entry_ids);

        // ── 3. Build rel_data / rel_cache (same shape as Entries source) ──────
        //
        // Entries source keys rel_data by entry_id because each entry has one
        // value per relationship field.  Here each *grid row* has an independent
        // relationship value per column, so we key by row_id instead.  The
        // Fields/Relationship handler receives row_id as its $entry_id argument
        // and does a straight $rel_data[$entry_id][$field_id] lookup — identical
        // contract, different scope key.
        //
        //   rel_data  [row_id][col_id][] = child_entry_id
        //   rel_cache [child_entry_id]   = ['title' => ..., ...]
        //
        $rel_data = [];
        $rel_cache = [];

        if ($this->rel_col_ids) {
            foreach ($all_grid_rows as $entry_grid_rows) {
                foreach ($entry_grid_rows as $grid_row) {
                    $row_id = (int)$grid_row['row_id'];
                    foreach ($this->rel_col_ids as $col_id) {
                        $child_id = (int)($grid_row['col_id_' . $col_id] ?? 0);
                        if ($child_id > 0) {
                            $rel_data[$row_id][$col_id][] = $child_id;
                        }
                    }
                }
            }

            // Collect all unique child IDs for a single batch resolve
            $all_child_ids = [];
            foreach ($rel_data as $row_rel) {
                foreach ($row_rel as $col_rel) {
                    foreach ($col_rel as $child_id) {
                        $all_child_ids[$child_id] = $child_id;
                    }
                }
            }

            if ($all_child_ids) {
                $rel_fields = $this->parseRelationshipFields();
                $rel_cache = ee('export:EntryService')
                    ->resolveRelatedEntries(array_values($all_child_ids), $rel_fields);
            }
        }

        // ── 4. Flatten: one export row per grid row ───────────────────────────
        $rows = [];
        foreach ($entry_ids as $entry_id) {
            $entry = $entry_map[$entry_id];
            $entry_grid_rows = $all_grid_rows[$entry_id] ?? [];

            foreach ($entry_grid_rows as $grid_row) {
                $row_id = (int)$grid_row['row_id'];

                $row = [
                    'entry_id' => $entry_id,
                    'entry_title' => $entry['title'],
                    'row_order' => (int)$grid_row['row_order'],
                ];

                foreach ($this->grid_columns as $col_id => $col_info) {
                    $col_key = 'col_id_' . $col_id;
                    $raw = $grid_row[$col_key] ?? null;

                    $row[$col_info['col_name']] = $this->processColumnValue(
                        $raw, $col_info, $row_id, $entry_id, $rel_data, $rel_cache
                    );
                }

                $rows[] = $this->cleanFields($row);
            }
        }

        $this->stream_offset += count($entry_ids);

        return $rows;
    }

    public function closeStream(): void
    {
    }

    // ── Column processing ─────────────────────────────────────────────────────

    /**
     * Route a single grid column value through the FieldsService handler for its
     * col_type, falling back to the raw value when no handler is registered.
     *
     * The col_info array is translated into the field_info shape expected by
     * AbstractField::process() so all existing handlers work without changes:
     *
     *   col_id       → field_id
     *   col_name     → field_name
     *   col_type     → field_type
     *   col_label    → field_label
     *   col_settings → field_settings
     *
     * rel_data / rel_cache are passed in the context array with the same keys
     * and structure used by the Entries source.  row_id acts as the entry_id
     * lookup key (see nextChunk() for rationale).
     */
    /**
     * Route a single grid column value through the FieldsService handler for its
     * col_type, falling back to the raw value when no handler is registered.
     *
     * The col_info array is translated into the field_info shape expected by
     * AbstractField::process() so all existing handlers work without changes:
     *
     *   col_id       → field_id
     *   col_name     → field_name
     *   col_type     → field_type
     *   col_label    → field_label
     *   col_settings → field_settings
     *
     * Note: field_id here is col_id, NOT a channel_fields.field_id.  Third-party
     * handlers that make additional DB queries by field_id or entry_id should check
     * $context['source_type'] === 'grid' and use $context['col_id'] /
     * $context['entry_id'] / $context['row_id'] instead.
     *
     * rel_data / rel_cache follow the identical shape used by the Entries source.
     * row_id is passed as the $entry_id lookup key (see nextChunk() for rationale).
     */
    protected function processColumnValue(
        mixed $raw,
        array $col_info,
        int   $row_id,
        int   $entry_id,
        array $rel_data,
        array $rel_cache
    ): mixed
    {
        $field_info = [
            'field_id' => (int)$col_info['col_id'],
            'field_name' => $col_info['col_name'],
            'field_type' => $col_info['col_type'],
            'field_label' => $col_info['col_label'] ?? $col_info['col_name'],
            'field_settings' => $col_info['col_settings'] ?? [],
        ];

        $field = ee('export:FieldsService')->getField($col_info['col_type']);

        if ($field) {
            return $field->process($raw, $field_info, $row_id, [
                'rel_data' => $rel_data,
                'rel_cache' => $rel_cache,
                // Disambiguation keys for third-party handlers
                'source_type' => 'grid',
                'row_id' => $row_id,
                'entry_id' => $entry_id,
                'col_id' => (int)$col_info['col_id'],
            ]);
        }

        return $raw ?? '';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function parseRelationshipFields(): array
    {
        $param = $this->getOption('relationship_fields', 'title');
        return array_filter(array_map('trim', explode('|', $param)));
    }

    // ── Validation ────────────────────────────────────────────────────────────

    protected function getValidator(): Validator
    {
        $validator = parent::getValidator();

        $validator->defineRule('validChannel', function ($key, $value) {
            return ee('export:EntryService')->getChannelId((string)$value) > 0
                ? true
                : 'channel not found';
        });

        $validator->defineRule('validGridField', function ($key, $value) {
            $channel = (string)$this->getOption('channel', '');
            $channel_id = ee('export:EntryService')->getChannelId($channel);
            return ee('export:EntryService')->getGridFieldId((string)$value, $channel_id) > 0
                ? true
                : 'grid field not found on channel';
        });

        return $validator;
    }
}
