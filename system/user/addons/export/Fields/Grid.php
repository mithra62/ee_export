<?php

namespace Mithra62\Export\Fields;

use Mithra62\Export\Plugins\AbstractField;

/**
 * Handles EE `grid` field types.
 *
 * Returns an array of row objects keyed by column name. Relationship columns
 * within the grid are resolved to ['entry_id' => X, 'title' => '...'] objects.
 *
 * Native formats (JSON, XML) receive the array directly.
 * Flat formats (CSV, XLSX) receive a JSON-encoded string via their flattenValue() helper.
 *
 * Context keys used:
 *   grid_data    — [field_id][entry_id][] = raw row array
 *   grid_columns — [field_id][col_id]     = col_info array (col_name, col_type, ...)
 *   rel_cache    — [child_entry_id]        = ['title' => ..., ...]
 */
class Grid extends AbstractField
{
    public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
    {
        $field_id = (int)$field_info['field_id'];
        $grid_data = $context['grid_data'] ?? [];
        $grid_columns = $context['grid_columns'] ?? [];
        $rel_cache = $context['rel_cache'] ?? [];

        $rows = $grid_data[$field_id][$entry_id] ?? [];
        $columns = $grid_columns[$field_id] ?? [];

        if (empty($rows)) {
            return [];
        }

        $export_rows = [];
        foreach ($rows as $raw_row) {
            $mapped_row = [];
            foreach ($columns as $col_id => $col_info) {
                $col_key = 'col_id_' . $col_id;
                $value = $raw_row[$col_key] ?? null;

                if ($col_info['col_type'] === 'relationship' && !is_null($value) && $value !== '') {
                    $child_id = (int)$value;
                    $resolved = $rel_cache[$child_id] ?? null;
                    $value = $resolved
                        ? ['entry_id' => $child_id, 'title' => $resolved['title']]
                        : ['entry_id' => $child_id];
                }

                $mapped_row[$col_info['col_name']] = $value ?? '';
            }
            $export_rows[] = $mapped_row;
        }

        return $export_rows;
    }
}
