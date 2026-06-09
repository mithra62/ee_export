<?php

namespace Mithra62\Export\Sources;

use CI_DB_result;
use ExpressionEngine\Service\Validation\Validator;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Entries extends AbstractSource
{
    protected array $rules = [
        'source' => 'required',
        'channel' => 'required|validChannel',
    ];

    protected int $stream_offset = 0;
    protected int $stream_chunk_size = 500;
    protected int $stream_channel_id = 0;
    protected array $channel_fields = [];
    protected array $grid_columns = [];   // [field_id => [col_id => col_info]]
    protected array $rel_field_ids = [];   // field_ids that are relationship type
    protected array $grid_field_ids = [];   // field_ids that are grid type
    protected array $fluid_field_ids = [];   // field_ids that are fluid_field type

    // -------------------------------------------------------------------------
    // AbstractSource contract
    // -------------------------------------------------------------------------

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
            throw new NoDataException("No entries found");
        }

        $this->setExportData($rows);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Streaming interface
    // -------------------------------------------------------------------------

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function openStream(): void
    {
        $this->stream_offset = (int)$this->getOption('offset', 0);
        $this->stream_chunk_size = (int)$this->getOption('chunk_size', 500);

        $channel_id = ee('export:EntryService')->getChannelId((string)$this->getOption('channel', ''));
        $this->stream_channel_id = $channel_id;

        $this->channel_fields = ee('export:EntryService')->getChannelFields($channel_id);

        foreach ($this->channel_fields as $field_id => $field_info) {
            switch ($field_info['field_type']) {
                case 'relationship':
                    $this->rel_field_ids[] = $field_id;
                    break;
                case 'grid':
                    $this->grid_field_ids[] = $field_id;
                    $this->grid_columns[$field_id] = ee('export:EntryService')->getGridColumns($field_id);
                    break;
                case 'fluid_field':
                    $this->fluid_field_ids[] = $field_id;
                    break;
            }
        }
    }

    public function nextChunk(): array
    {
        $channel_id = $this->stream_channel_id;
        $limit = $this->stream_chunk_size;

        if ($this->getOption('limit')) {
            $hard_limit = (int)$this->getOption('limit') + (int)$this->getOption('offset', 0);
            $remaining = $hard_limit - $this->stream_offset;
            if ($remaining <= 0) {
                return [];
            }
            $limit = min($limit, $remaining);
        }

        $query = ee()->db
            ->select('entry_id, title, url_title, status, entry_date, expiration_date, author_id, edit_date')
            ->from('channel_titles')
            ->where('channel_id', $channel_id)
            ->where('status', $this->getOption('status', 'open'));

        if ($this->getOption('author_id')) {
            $query->where('author_id', (int)$this->getOption('author_id'));
        }

        $result = $query->limit($limit, $this->stream_offset)->get();

        if (!($result instanceof CI_DB_result) || $result->num_rows() === 0) {
            return [];
        }

        $entry_rows = $result->result_array();
        $entry_ids = array_column($entry_rows, 'entry_id');
        $entry_ids = array_map('intval', $entry_ids);

        // Batch-load supporting data
        $field_data = ee('export:EntryService')->batchFieldData($entry_ids);
        $cat_data = ee('export:EntryService')->batchCategoryNames($entry_ids);

        $rel_data = [];
        $rel_cache = [];
        if ($this->rel_field_ids) {
            $rel_data = ee('export:EntryService')->batchRelationshipIds($entry_ids, $this->rel_field_ids);
            $child_ids = [];
            foreach ($rel_data as $entry_rel) {
                foreach ($entry_rel as $field_rel) {
                    foreach ($field_rel as $child_id) {
                        $child_ids[$child_id] = $child_id;
                    }
                }
            }
            if ($child_ids) {
                $rel_fields = $this->parseRelationshipFields();
                $rel_cache = ee('export:EntryService')->resolveRelatedEntries(array_values($child_ids), $rel_fields);
            }
        }

        $grid_data = [];
        foreach ($this->grid_field_ids as $field_id) {
            $grid_data[$field_id] = ee('export:EntryService')->batchGridData($field_id, $entry_ids);
        }

        // Batch-load fluid data — replaces per-entry getFluidData() / getFluidFieldData() calls.
        // Two passes: first fetch all fluid_field_data rows for the chunk, then batch-fetch
        // sub-field values grouped by field_id to avoid N+1 inside FluidField::process().
        $fluid_instances  = [];
        $fluid_values     = [];
        $fluid_grid_data  = [];
        if ($this->fluid_field_ids) {
            $fluid_instances = ee('export:EntryService')
                ->batchFluidInstances($entry_ids, $this->fluid_field_ids);

            // Segregate scalar vs grid sub-fields so we can batch each correctly
            $scalar_data_ids = []; // [sub_field_id => [field_data_id, ...]]
            $grid_data_ids   = []; // [sub_field_id => [fluid_instance_id, ...]]

            foreach ($fluid_instances as $entry_fluid) {
                foreach ($entry_fluid as $instances) {
                    foreach ($instances as $inst) {
                        $sub_field_id = (int) $inst['field_id'];
                        $sub_type     = $this->channel_fields[$sub_field_id]['field_type'] ?? '';

                        if ($sub_type === 'grid') {
                            $grid_data_ids[$sub_field_id][] = (int) $inst['id'];
                        } else {
                            $scalar_data_ids[$sub_field_id][] = (int) $inst['field_data_id'];
                        }
                    }
                }
            }

            foreach ($scalar_data_ids as $sub_field_id => $data_ids) {
                $fluid_values[$sub_field_id] = ee('export:EntryService')
                    ->batchFluidSubFieldValues($sub_field_id, array_unique($data_ids));
            }

            foreach ($grid_data_ids as $sub_field_id => $instance_ids) {
                $fluid_grid_data[$sub_field_id] = ee('export:EntryService')
                    ->batchFluidGridData($sub_field_id, array_unique($instance_ids));
            }
        }

        // Build rows
        $rows = [];
        foreach ($entry_rows as $entry) {
            $entry_id = (int)$entry['entry_id'];
            $row = $this->buildRow(
                $entry, $entry_id, $field_data, $cat_data,
                $rel_data, $rel_cache, $grid_data,
                $fluid_instances, $fluid_values, $fluid_grid_data
            );
            $rows[] = $this->cleanFields($row);
        }

        $this->stream_offset += count($entry_rows);

        return $rows;
    }

    public function closeStream(): void
    {
    }

    // -------------------------------------------------------------------------
    // Row building
    // -------------------------------------------------------------------------

    protected function buildRow(
        array $entry,
        int   $entry_id,
        array $field_data,
        array $cat_data,
        array $rel_data,
        array $rel_cache,
        array $grid_data,
        array $fluid_instances = [],
        array $fluid_values    = [],
        array $fluid_grid_data = []
    ): array {
        $row = [
            'entry_id'        => $entry['entry_id'],
            'title'           => $entry['title'],
            'url_title'       => $entry['url_title'],
            'status'          => $entry['status'],
            'entry_date'      => $entry['entry_date'],
            'expiration_date' => $entry['expiration_date'],
            'author_id'       => $entry['author_id'],
            'edit_date'       => $entry['edit_date'],
            'categories'      => $cat_data[$entry_id] ?? '',
        ];

        $raw_fields = $field_data[$entry_id] ?? [];

        foreach ($this->channel_fields as $field_id => $field_info) {
            $col       = 'field_id_' . $field_id;
            $raw_value = $raw_fields[$col] ?? null;

            $row[$field_info['field_name']] = $this->processFieldValue(
                $raw_value, $field_info, $entry_id,
                $rel_data, $rel_cache, $grid_data,
                $fluid_instances, $fluid_values, $fluid_grid_data
            );
        }

        return $row;
    }

    protected function processFieldValue(
        mixed $raw_value,
        array $field_info,
        int   $entry_id,
        array $rel_data,
        array $rel_cache,
        array $grid_data,
        array $fluid_instances = [],
        array $fluid_values    = [],
        array $fluid_grid_data = []
    ): mixed {
        $field = ee('export:FieldsService')->getField($field_info['field_type']);

        if ($field) {
            return $field->process($raw_value, $field_info, $entry_id, [
                'rel_data'        => $rel_data,
                'rel_cache'       => $rel_cache,
                'grid_data'       => $grid_data,
                'channel_fields'  => $this->channel_fields,
                'grid_columns'    => $this->grid_columns,
                'fluid_instances' => $fluid_instances,
                'fluid_values'    => $fluid_values,
                'fluid_grid_data' => $fluid_grid_data,
            ]);
        }

        return $raw_value ?? '';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function parseRelationshipFields(): array
    {
        $param = $this->getOption('relationship_fields', 'title');
        return array_filter(array_map('trim', explode('|', $param)));
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    protected function getValidator(): Validator
    {
        $validator = parent::getValidator();
        $validator->defineRule('validChannel', function ($key, $value) {
            return ee('export:EntryService')->getChannelId((string)$value) > 0
                ? true
                : 'channel not found';
        });

        return $validator;
    }
}
