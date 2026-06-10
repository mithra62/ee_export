<?php

namespace Mithra62\Export\Plugins;

abstract class AbstractField
{
    /**
     * Process a single field value for export.
     *
     * @param mixed $raw_value Raw value from channel_data.field_id_X (or member equivalent)
     * @param array $field_info Field definition row: field_id, field_name, field_type, field_label, field_settings
     * @param int $entry_id Entry ID (or member_id when invoked from the Members source)
     * @param array $context Pre-fetched batch data passed by the Source layer.
     *                           Standard keys for channel entries:
     *                             rel_data       — [entry_id][field_id][] = child_entry_id
     *                             rel_cache      — [entry_id] = ['title' => ..., ...]
     *                             grid_data      — [field_id][entry_id][] = row array
     *                             channel_fields — [field_id] = field_info array
     *                             grid_columns   — [field_id][col_id] = col_info array
     */
    abstract public function process(
        mixed $raw_value,
        array $field_info,
        int   $entry_id,
        array $context = []
    ): mixed;
}
