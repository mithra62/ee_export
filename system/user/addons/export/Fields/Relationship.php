<?php

namespace Mithra62\Export\Fields;

use Mithra62\Export\Plugins\AbstractField;

/**
 * Handles EE `relationship` field types.
 *
 * Returns an array of related entry objects. Each item has at minimum an
 * `entry_id` key; if the related entry was resolved it also has a `title` key.
 *
 * Native formats (JSON, XML) receive the array directly.
 * Flat formats (CSV, XLSX) receive a JSON-encoded string via their flattenValue() helper.
 *
 * Context keys used:
 *   rel_data  — [entry_id][field_id][] = child_entry_id
 *   rel_cache — [child_entry_id] = ['title' => ..., ...]
 */
class Relationship extends AbstractField
{
    public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
    {
        $field_id = (int)$field_info['field_id'];
        $rel_data = $context['rel_data'] ?? [];
        $rel_cache = $context['rel_cache'] ?? [];

        $child_ids = $rel_data[$entry_id][$field_id] ?? [];
        if (empty($child_ids)) {
            return [];
        }

        $parts = [];
        foreach ($child_ids as $child_id) {
            $resolved = $rel_cache[$child_id] ?? null;
            $parts[] = $resolved
                ? ['entry_id' => $child_id, 'title' => $resolved['title']]
                : ['entry_id' => $child_id];
        }

        return $parts;
    }
}
