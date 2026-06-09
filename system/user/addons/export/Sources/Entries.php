<?php
namespace Mithra62\Export\Sources;

use CI_DB_result;
use ExpressionEngine\Service\Validation\Validator;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Entries extends AbstractSource
{
    protected array $rules = [
        'source'  => 'required',
        'channel' => 'required|validChannel',
    ];

    protected int $stream_offset    = 0;
    protected int $stream_chunk_size = 500;
    protected int $stream_channel_id = 0;
    protected array $channel_fields  = [];
    protected array $grid_columns    = [];   // [field_id => [col_id => col_info]]
    protected array $rel_field_ids   = [];   // field_ids that are relationship type
    protected array $grid_field_ids  = [];   // field_ids that are grid type
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

    public function supportsStreaming(): bool { return true; }

    public function openStream(): void
    {
        $this->stream_offset     = (int) $this->getOption('offset', 0);
        $this->stream_chunk_size = (int) $this->getOption('chunk_size', 500);

        $channel_id = ee('export:EntryService')->getChannelId((string) $this->getOption('channel', ''));
        $this->stream_channel_id = $channel_id;

        $this->channel_fields = ee('export:EntryService')->getChannelFields($channel_id);

        foreach ($this->channel_fields as $field_id => $field_info) {
            switch ($field_info['field_type']) {
                case 'relationship':
                    $this->rel_field_ids[] = $field_id;
                    break;
                case 'grid':
                    $this->grid_field_ids[]          = $field_id;
                    $this->grid_columns[$field_id]    = ee('export:EntryService')->getGridColumns($field_id);
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
        $limit      = $this->stream_chunk_size;

        if ($this->getOption('limit')) {
            $hard_limit = (int) $this->getOption('limit') + (int) $this->getOption('offset', 0);
            $remaining  = $hard_limit - $this->stream_offset;
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
            $query->where('author_id', (int) $this->getOption('author_id'));
        }

        $result = $query->limit($limit, $this->stream_offset)->get();

        if (!($result instanceof CI_DB_result) || $result->num_rows() === 0) {
            return [];
        }

        $entry_rows = $result->result_array();
        $entry_ids  = array_column($entry_rows, 'entry_id');
        $entry_ids  = array_map('intval', $entry_ids);

        // Batch-load supporting data
        $field_data = ee('export:EntryService')->batchFieldData($entry_ids);
        $cat_data   = ee('export:EntryService')->batchCategoryNames($entry_ids);

        $rel_data  = [];
        $rel_cache = [];
        if ($this->rel_field_ids) {
            $rel_data  = ee('export:EntryService')->batchRelationshipIds($entry_ids, $this->rel_field_ids);
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
                $rel_cache  = ee('export:EntryService')->resolveRelatedEntries(array_values($child_ids), $rel_fields);
            }
        }

        $grid_data = [];
        foreach ($this->grid_field_ids as $field_id) {
            $grid_data[$field_id] = ee('export:EntryService')->batchGridData($field_id, $entry_ids);
        }

        // Build rows
        $rows = [];
        foreach ($entry_rows as $entry) {
            $entry_id = (int) $entry['entry_id'];
            $row      = $this->buildRow($entry, $entry_id, $field_data, $cat_data, $rel_data, $rel_cache, $grid_data);
            $rows[]   = $this->cleanFields($row);
        }

        $this->stream_offset += count($entry_rows);

        return $rows;
    }

    public function closeStream(): void {}

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
        array $grid_data
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
                $raw_value, $field_info, $entry_id, $rel_data, $rel_cache, $grid_data
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
        array $grid_data
    ): mixed {
        $field_id   = (int) $field_info['field_id'];
        $field_type = $field_info['field_type'];

        return match ($field_type) {
            'date'        => (int) ($raw_value ?? 0),
            'file'        => ee('export:EntryService')->getImageUrl($raw_value),
            'relationship'=> $this->formatRelationship($entry_id, $field_id, $rel_data, $rel_cache),
            'grid'        => $this->formatGrid($entry_id, $field_id, $grid_data, $rel_data, $rel_cache),
            'fluid_field' => $this->formatFluid($entry_id, $field_id),
            default       => $raw_value ?? '',
        };
    }

    // -------------------------------------------------------------------------
    // Complex field formatters
    // -------------------------------------------------------------------------

    protected function formatRelationship(int $entry_id, int $field_id, array $rel_data, array $rel_cache): array
    {
        $child_ids = $rel_data[$entry_id][$field_id] ?? [];
        if (empty($child_ids)) {
            return [];
        }

        $parts = [];
        foreach ($child_ids as $child_id) {
            $resolved = $rel_cache[$child_id] ?? null;
            $parts[]  = $resolved
                ? ['entry_id' => $child_id, 'title' => $resolved['title']]
                : ['entry_id' => $child_id];
        }

        return $parts;
    }

    protected function formatGrid(int $entry_id, int $field_id, array $grid_data, array $rel_data, array $rel_cache): array
    {
        $rows    = $grid_data[$field_id][$entry_id] ?? [];
        $columns = $this->grid_columns[$field_id] ?? [];

        if (empty($rows)) {
            return [];
        }

        $export_rows = [];
        foreach ($rows as $raw_row) {
            $mapped_row = [];
            foreach ($columns as $col_id => $col_info) {
                $col_key = 'col_id_' . $col_id;
                $value   = $raw_row[$col_key] ?? null;

                if ($col_info['col_type'] === 'relationship' && !is_null($value) && $value !== '') {
                    $child_id = (int) $value;
                    $resolved = $rel_cache[$child_id] ?? null;
                    $value    = $resolved
                        ? ['entry_id' => $child_id, 'title' => $resolved['title']]
                        : ['entry_id' => $child_id];
                }

                $mapped_row[$col_info['col_name']] = $value ?? '';
            }
            $export_rows[] = $mapped_row;
        }

        return $export_rows;
    }

    protected function formatFluid(int $entry_id, int $field_id): array
    {
        $instances = ee('export:EntryService')->getFluidData($entry_id, $field_id);
        if (empty($instances)) {
            return [];
        }

        $export = [];
        foreach ($instances as $instance) {
            $sub_field_id = (int) $instance['field_id'];
            $field_info   = $this->channel_fields[$sub_field_id] ?? null;

            $item = [
                'type'       => $field_info['field_type'] ?? 'unknown',
                'field_name' => $field_info['field_name'] ?? ('field_' . $sub_field_id),
                'order'      => (int) $instance['order'],
            ];

            if (($field_info['field_type'] ?? '') === 'grid') {
                $col_map = [];
                $cols    = $this->grid_columns[$sub_field_id]
                    ?? ee('export:EntryService')->getGridColumns($sub_field_id);

                foreach ($cols as $col_id => $col_info) {
                    $col_map[$col_info['col_name']] = 'col_id_' . $col_id;
                }

                $item['rows'] = ee('export:EntryService')->getGridData(
                    $sub_field_id, $entry_id, array_flip($col_map), (int) $instance['id']
                );
            } else {
                $item['value'] = ee('export:EntryService')->getFluidFieldData(
                    $entry_id, $field_id, $sub_field_id
                );
            }

            $export[] = $item;
        }

        return $export;
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
            return ee('export:EntryService')->getChannelId((string) $value) > 0
                ? true
                : 'channel not found';
        });

        return $validator;
    }
}
