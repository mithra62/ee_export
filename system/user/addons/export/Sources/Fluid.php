<?php

namespace Mithra62\Export\Sources;

use CI_DB_result;
use ExpressionEngine\Service\Validation\Validator;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

/**
 * Fluid source — exports EE Fluid field instances as a flat tabular dataset.
 *
 * Output shape (one row per fluid instance):
 *   entry_id | entry_title | instance_order | sub_field_id | sub_field_type | sub_field_label | value
 *
 * Each row represents a single Fluid field block (instance). The `value` column
 * contains the processed output for that instance, routed through FieldsService
 * using the same field-handler pipeline as the Entries, Members, and Grid sources:
 *
 *   date / file          → via registered field handlers
 *   relationship         → resolved to [{entry_id, title}, ...] via batch query
 *                          (EE stores fluid relationship values in the `relationships`
 *                          table keyed by fluid_field_data_id, not in channel_data_field_X)
 *   grid                 → resolved via Fields/Grid handler; relationship columns
 *                          within the nested grid are also batch-resolved
 *   all other types      → routed through FieldsService, falling back to raw value
 *
 * Disambiguation context passed to all field handlers:
 *   source_type       = 'fluid'
 *   fluid_instance_id = fluid_field_data.id (PK of the instance)
 *   fluid_field_id    = the parent fluid field's channel_fields.field_id
 *   entry_id          = the entry this instance belongs to
 *
 * For relationship and grid sub-fields, `instance_id` is passed as the $entry_id
 * argument to the handler — the same disambiguation trick the Grid source uses with
 * row_id — so the handler's rel_data / grid_data lookups resolve correctly.
 *
 * Column selection:
 *   fields="entry_id|instance_order|value"   whitelist — return only those columns
 *   exclude="sub_field_id"                   blacklist — remove those columns
 *
 * Streaming is supported — entries are processed in configurable chunks.
 */
class Fluid extends AbstractSource
{
    protected array $rules = [
        'source' => 'required',
        'channel' => 'required|validChannel',
        'field' => 'required|validFluidField',
    ];

    // ── Streaming state ──────────────────────────────────────────────────────

    protected int $stream_offset = 0;
    protected int $stream_chunk_size = 500;
    protected int $stream_channel_id = 0;
    protected int $stream_field_id = 0;

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
            throw new NoDataException("No fluid field instances found");
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

        $this->stream_channel_id = ee('export:EntryService')
            ->getChannelId((string)$this->getOption('channel', ''));

        $this->stream_field_id = ee('export:EntryService')
            ->getFluidFieldId((string)$this->getOption('field', ''), $this->stream_channel_id);
    }

    public function nextChunk(): array
    {
        $channel_id = $this->stream_channel_id;
        $fluid_field_id = $this->stream_field_id;
        $limit = $this->stream_chunk_size;

        // Respect hard limit (applies to entries, not instances)
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
            ->select('entry_id, title')
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

        $result = $query->limit($limit, $this->stream_offset)->get();

        if (!($result instanceof CI_DB_result) || $result->num_rows() === 0) {
            return [];
        }

        $entry_rows = $result->result_array();
        $entry_ids = array_map('intval', array_column($entry_rows, 'entry_id'));

        $entry_map = [];
        foreach ($entry_rows as $entry) {
            $entry_map[(int)$entry['entry_id']] = $entry;
        }

        // ── 2. Batch-load fluid instances ─────────────────────────────────────
        //
        // Returns [entry_id][fluid_field_id][] = instance_row
        // Each instance_row: { id, entry_id, fluid_field_id, field_id, field_data_id, order }
        //
        $all_instances = ee('export:EntryService')
            ->batchFluidInstances($entry_ids, [$fluid_field_id]);

        // ── 3. Collect sub-field IDs and classify by storage type ─────────────
        $sub_field_ids = [];
        foreach ($all_instances as $entry_instances) {
            foreach ($entry_instances[$fluid_field_id] ?? [] as $inst) {
                $sub_field_ids[(int)$inst['field_id']] = (int)$inst['field_id'];
            }
        }

        if (empty($sub_field_ids)) {
            $this->stream_offset += count($entry_ids);
            return [];
        }

        $sub_field_defs = ee('export:EntryService')
            ->getFieldDefinitions(array_values($sub_field_ids));

        // Bucket instance IDs by sub-field storage type
        $grid_inst_ids_by_field = [];  // [sfid][] = instance_id
        $rel_field_ids = [];  // sfid => sfid  (relationship sub-fields)
        $rel_instance_ids = [];  // instance_id => instance_id
        $data_ids_by_field = [];  // [sfid][] = field_data_id  (all other types)

        foreach ($all_instances as $entry_instances) {
            foreach ($entry_instances[$fluid_field_id] ?? [] as $inst) {
                $sfid = (int)$inst['field_id'];
                $def = $sub_field_defs[$sfid] ?? null;
                if (!$def) {
                    continue;
                }

                switch ($def['field_type']) {
                    case 'grid':
                        $grid_inst_ids_by_field[$sfid][] = (int)$inst['id'];
                        break;

                    case 'relationship':
                        $rel_field_ids[$sfid] = $sfid;
                        $rel_instance_ids[(int)$inst['id']] = (int)$inst['id'];
                        break;

                    default:
                        $data_ids_by_field[$sfid][] = (int)$inst['field_data_id'];
                        break;
                }
            }
        }

        // ── 4. Batch-load grid rows and column definitions ────────────────────
        //
        // grid_rows_by_field  [sfid][instance_id][] = row
        // grid_columns_cache  [sfid][col_id]        = col_info
        //
        // Fields/Grid expects:
        //   $context['grid_data'][$field_id][$entry_id][] = row
        //   $context['grid_columns'][$field_id]
        //
        // We pass instance_id as the $entry_id argument, so grid_rows_by_field
        // already has the correct shape.
        //
        $grid_rows_by_field = [];
        $grid_columns_cache = [];

        foreach ($grid_inst_ids_by_field as $sfid => $inst_ids) {
            $unique = array_values(array_unique($inst_ids));
            if ($unique) {
                $grid_rows_by_field[$sfid] = ee('export:EntryService')
                    ->batchFluidGridData($sfid, $unique);
                $grid_columns_cache[$sfid] = ee('export:EntryService')
                    ->getGridColumns($sfid);
            }
        }

        // ── 5. Batch-load non-grid / non-relationship sub-field values ────────
        //
        // batchFluidSubFieldValues loads from channel_data_field_X by field_data_id.
        //
        $values_by_field = [];
        foreach ($data_ids_by_field as $sfid => $data_ids) {
            $unique = array_values(array_unique($data_ids));
            if ($unique) {
                $values_by_field[$sfid] = ee('export:EntryService')
                    ->batchFluidSubFieldValues($sfid, $unique);
            }
        }

        // ── 6. Batch-resolve relationship data ────────────────────────────────
        //
        // Two sources of relationship child IDs:
        //   a) Relationship sub-fields: stored in `relationships` table with
        //      fluid_field_data_id = instance_id (NOT in channel_data_field_X).
        //   b) Relationship columns inside nested grid sub-fields: stored as raw
        //      integers in col_id_X cells, same as top-level Grid source.
        //
        // rel_data  [instance_id][field_id][] = child_entry_id
        //   — used by Fields/Relationship; instance_id is passed as $entry_id
        // rel_cache [child_entry_id] = ['title' => ..., ...]
        //   — shared across both relationship sub-fields and grid relationship cols
        //
        $rel_data = [];
        $all_child_ids = [];

        // 6a. Relationship sub-fields
        if ($rel_field_ids && $rel_instance_ids) {
            $rel_data = ee('export:EntryService')->batchFluidRelationshipIds(
                array_values($rel_instance_ids),
                array_values($rel_field_ids)
            );

            foreach ($rel_data as $inst_rel) {
                foreach ($inst_rel as $child_list) {
                    foreach ($child_list as $cid) {
                        $all_child_ids[$cid] = $cid;
                    }
                }
            }
        }

        // 6b. Relationship columns inside grid sub-fields
        foreach ($grid_rows_by_field as $sfid => $rows_by_inst) {
            $cols = $grid_columns_cache[$sfid] ?? [];
            foreach ($cols as $col_info) {
                if ($col_info['col_type'] !== 'relationship') {
                    continue;
                }
                $col_key = 'col_id_' . $col_info['col_id'];
                foreach ($rows_by_inst as $grid_rows) {
                    foreach ($grid_rows as $grid_row) {
                        $cid = (int)($grid_row[$col_key] ?? 0);
                        if ($cid > 0) {
                            $all_child_ids[$cid] = $cid;
                        }
                    }
                }
            }
        }

        $rel_cache = [];
        if ($all_child_ids) {
            $rel_fields = $this->parseRelationshipFields();
            $rel_cache = ee('export:EntryService')
                ->resolveRelatedEntries(array_values($all_child_ids), $rel_fields);
        }

        // ── 7. Build output rows ──────────────────────────────────────────────
        $rows = [];
        foreach ($entry_ids as $entry_id) {
            $entry = $entry_map[$entry_id];
            $entry_instances = $all_instances[$entry_id][$fluid_field_id] ?? [];

            foreach ($entry_instances as $inst) {
                $sfid = (int)$inst['field_id'];
                $def = $sub_field_defs[$sfid] ?? null;
                if (!$def) {
                    continue;
                }

                $value = $this->processInstanceValue(
                    $inst,
                    $def,
                    $values_by_field,
                    $grid_rows_by_field,
                    $grid_columns_cache,
                    $rel_data,
                    $rel_cache
                );

                $row = [
                    'entry_id' => $entry_id,
                    'entry_title' => $entry['title'],
                    'instance_order' => (int)$inst['order'],
                    'sub_field_id' => $sfid,
                    'sub_field_type' => $def['field_type'],
                    'sub_field_label' => $def['field_label'],
                    'value' => $value,
                ];

                $rows[] = $this->cleanFields($row);
            }
        }

        $this->stream_offset += count($entry_ids);

        return $rows;
    }

    public function closeStream(): void
    {
    }

    // ── Value processing ──────────────────────────────────────────────────────

    /**
     * Route a single fluid instance value through the FieldsService handler pipeline.
     *
     * Sub-field type determines which handler — and which context keys — are used:
     *
     *   relationship
     *     Passes instance_id as $entry_id so Fields/Relationship resolves
     *     rel_data[$instance_id][$field_id] correctly.
     *
     *   grid
     *     Passes grid_data[$sfid][$instance_id][] = rows and grid_columns[$sfid]
     *     so Fields/Grid resolves rows and inline relationship columns correctly.
     *     Passes instance_id as $entry_id.
     *
     *   all other types
     *     Raw value looked up from values_by_field, passed to any registered
     *     handler.  entry_id (real entry) is used as the $entry_id argument.
     *
     * The disambiguation keys source_type / fluid_instance_id / fluid_field_id /
     * entry_id are always present so third-party handlers can detect Fluid context.
     */
    protected function processInstanceValue(
        array $inst,
        array $sub_field_def,
        array $values_by_field,
        array $grid_rows_by_field,
        array $grid_columns_cache,
        array $rel_data,
        array $rel_cache
    ): mixed
    {
        $sfid = (int)$inst['field_id'];
        $instance_id = (int)$inst['id'];
        $entry_id = (int)$inst['entry_id'];
        $field_type = $sub_field_def['field_type'];

        $base_context = [
            'source_type' => 'fluid',
            'fluid_instance_id' => $instance_id,
            'fluid_field_id' => (int)$inst['fluid_field_id'],
            'entry_id' => $entry_id,
        ];

        // ── Relationship sub-field ────────────────────────────────────────────
        //
        // rel_data is keyed by instance_id; pass instance_id as $entry_id so
        // Fields/Relationship resolves rel_data[$entry_id][$field_id] correctly.
        //
        if ($field_type === 'relationship') {
            $handler = ee('export:FieldsService')->getField('relationship');
            if ($handler) {
                return $handler->process(null, $sub_field_def, $instance_id, $base_context + [
                        'rel_data' => $rel_data,
                        'rel_cache' => $rel_cache,
                    ]);
            }
            // Fallback: return raw child IDs from rel_data
            return $rel_data[$instance_id][$sfid] ?? [];
        }

        // ── Grid sub-field ────────────────────────────────────────────────────
        //
        // Build grid_data[$sfid][$instance_id][] = rows so Fields/Grid resolves
        // grid_data[$field_id][$entry_id] when we pass instance_id as $entry_id.
        //
        if ($field_type === 'grid') {
            $handler = ee('export:FieldsService')->getField('grid');
            if ($handler) {
                $grid_data = [
                    $sfid => $grid_rows_by_field[$sfid] ?? [],
                ];
                $grid_columns = [
                    $sfid => $grid_columns_cache[$sfid] ?? [],
                ];

                return $handler->process(null, $sub_field_def, $instance_id, $base_context + [
                        'grid_data' => $grid_data,
                        'grid_columns' => $grid_columns,
                        'rel_cache' => $rel_cache,
                    ]);
            }

            // Fallback: manual serialization if Fields/Grid is not registered
            $grid_rows = $grid_rows_by_field[$sfid][$instance_id] ?? [];
            if (empty($grid_rows)) {
                return [];
            }
            $grid_columns = $grid_columns_cache[$sfid] ?? [];
            $serialized = [];
            foreach ($grid_rows as $grid_row) {
                $mapped = [];
                foreach ($grid_columns as $col_id => $col_info) {
                    $mapped[$col_info['col_name']] = $grid_row['col_id_' . $col_id] ?? null;
                }
                $serialized[] = $mapped;
            }
            return $serialized;
        }

        // ── All other sub-fields ──────────────────────────────────────────────
        $data_id = (int)$inst['field_data_id'];
        $raw_value = $values_by_field[$sfid][$data_id] ?? null;

        $handler = ee('export:FieldsService')->getField($field_type);
        if ($handler) {
            return $handler->process($raw_value, $sub_field_def, $entry_id, $base_context);
        }

        return $raw_value ?? '';
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

        $validator->defineRule('validFluidField', function ($key, $value) {
            $channel = (string)$this->getOption('channel', '');
            $channel_id = ee('export:EntryService')->getChannelId($channel);
            return ee('export:EntryService')->getFluidFieldId((string)$value, $channel_id) > 0
                ? true
                : 'fluid_field not found on channel';
        });

        return $validator;
    }
}
