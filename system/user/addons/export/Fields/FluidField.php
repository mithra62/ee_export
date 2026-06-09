<?php

namespace Mithra62\Export\Fields;

use Mithra62\Export\Plugins\AbstractField;

/**
 * Handles EE `fluid_field` field types.
 *
 * Returns an array of typed instances preserving order and structure.
 * Each item has: type, field_name, order, and either a `value` (scalar fields)
 * or `rows` (nested grid fields).
 *
 * Native formats (JSON, XML) receive the array directly.
 * Flat formats (CSV, XLSX) receive a JSON-encoded string via their flattenValue() helper.
 *
 * Context keys used:
 *   channel_fields — [field_id] = field_info array (for resolving sub-field types)
 *   grid_columns   — [field_id] = column definitions (for nested grids)
 */
class FluidField extends AbstractField
{
    public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
    {
        $field_id = (int)$field_info['field_id'];
        $channel_fields = $context['channel_fields'] ?? [];
        $grid_columns = $context['grid_columns'] ?? [];

        $instances = ee('export:EntryService')->getFluidData($entry_id, $field_id);
        if (empty($instances)) {
            return [];
        }

        $export = [];
        foreach ($instances as $instance) {
            $sub_field_id = (int)$instance['field_id'];
            $sub_info = $channel_fields[$sub_field_id] ?? null;

            $item = [
                'type' => $sub_info['field_type'] ?? 'unknown',
                'field_name' => $sub_info['field_name'] ?? ('field_' . $sub_field_id),
                'order' => (int)$instance['order'],
            ];

            if (($sub_info['field_type'] ?? '') === 'grid') {
                $col_map = [];
                $cols = $grid_columns[$sub_field_id]
                    ?? ee('export:EntryService')->getGridColumns($sub_field_id);

                foreach ($cols as $col_id => $col_info) {
                    $col_map[$col_info['col_name']] = 'col_id_' . $col_id;
                }

                $item['rows'] = ee('export:EntryService')->getGridData(
                    $sub_field_id,
                    $entry_id,
                    array_flip($col_map),
                    (int)$instance['id']
                );
            } else {
                $item['value'] = ee('export:EntryService')->getFluidFieldData(
                    $entry_id,
                    $field_id,
                    $sub_field_id
                );
            }

            $export[] = $item;
        }

        return $export;
    }
}
