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
        $field_id        = (int) $field_info['field_id'];
        $channel_fields  = $context['channel_fields']  ?? [];
        $grid_columns    = $context['grid_columns']    ?? [];
        $fluid_instances = $context['fluid_instances'] ?? [];
        $fluid_values    = $context['fluid_values']    ?? [];
        $fluid_grid_data = $context['fluid_grid_data'] ?? [];

        // Use pre-fetched batch data; fall back to live queries when context is absent
        // (e.g. when called from a non-streaming path).
        $instances = $fluid_instances[$entry_id][$field_id]
            ?? ee('export:EntryService')->getFluidData($entry_id, $field_id);

        if (empty($instances)) {
            return [];
        }

        $export = [];
        foreach ($instances as $instance) {
            $sub_field_id = (int) $instance['field_id'];
            $fluid_id     = (int) $instance['id'];          // PK of fluid_field_data row
            $data_id      = (int) $instance['field_data_id']; // FK into channel_data_field_X
            $sub_info     = $channel_fields[$sub_field_id] ?? null;
            $sub_type     = $sub_info['field_type'] ?? 'unknown';

            $item = [
                'type'       => $sub_type,
                'field_name' => $sub_info['field_name'] ?? ('field_' . $sub_field_id),
                'order'      => (int) $instance['order'],
            ];

            if ($sub_type === 'grid') {
                $cols    = $grid_columns[$sub_field_id]
                    ?? ee('export:EntryService')->getGridColumns($sub_field_id);
                $col_map = [];
                foreach ($cols as $col_id => $col_info) {
                    $col_map[$col_info['col_name']] = 'col_id_' . $col_id;
                }

                // Use pre-fetched grid rows keyed by fluid instance PK
                $raw_grid_rows       = $fluid_grid_data[$sub_field_id][$fluid_id] ?? null;
                $item['rows'] = $raw_grid_rows !== null
                    ? $this->mapGridRows($raw_grid_rows, array_flip($col_map))
                    : ee('export:EntryService')->getGridData(
                        $sub_field_id, $entry_id, array_flip($col_map), $fluid_id
                    );
            } else {
                // Use pre-fetched scalar value; fall back to live query
                $item['value'] = array_key_exists($sub_field_id, $fluid_values)
                    ? (string) ($fluid_values[$sub_field_id][$data_id] ?? '')
                    : ee('export:EntryService')->getFluidFieldData($entry_id, $field_id, $sub_field_id);
            }

            $export[] = $item;
        }

        return $export;
    }

    /**
     * Map raw DB grid rows to named-column arrays, matching the same logic used
     * in Fields/Grid but operating on an already-fetched row set.
     */
    protected function mapGridRows(array $raw_rows, array $col_key_map): array
    {
        // col_key_map: ['col_name' => 'col_id_X', ...]  (already flipped by caller)
        $export_rows = [];
        foreach ($raw_rows as $raw_row) {
            $mapped = [];
            foreach ($col_key_map as $col_name => $col_key) {
                $mapped[$col_name] = $raw_row[$col_key] ?? '';
            }
            $export_rows[] = $mapped;
        }
        return $export_rows;
    }
}
