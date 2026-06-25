<?php

namespace Mithra62\Export\Traits;

/**
 * Shared `search:field_name="value"` filter logic for the Entries, Grid, and
 * Fluid sources. Each of those sources builds its own independent
 * channel_titles query in nextChunk() rather than delegating to a common
 * base, so this trait centralises the one piece of logic all three need
 * identically instead of tripling it.
 *
 * Core channel_titles columns are matched directly on the streaming query.
 * Custom field matching is delegated to the ChannelEntry model rather than
 * reimplemented in raw SQL: the model already knows whether a field's data
 * lives in the shared `channel_data` table or its own EE7 split-storage
 * table (`channel_data_field_X`) and joins accordingly. We only pull back
 * matching entry_ids and fold them into the existing streaming query via
 * where_in() — this also avoids reintroducing a column-ambiguity bug a
 * manual JOIN against channel_data would cause (channel_data has its own
 * entry_id/channel_id columns, which collide with the unqualified ones
 * already selected by Entries/Grid/Fluid).
 *
 * Matching is exact (`=`), the same as Sources/Members.php::applySearchFilters() —
 * not LIKE-based — so it stays index-friendly on large channels.
 *
 * Consumers must populate `$this->channel_fields` (field_id => field_info,
 * each with a 'field_name' key) in their own openStream(), the same array
 * Entries.php already loads via EntryService::getChannelFields().
 */
trait SearchFilterTrait
{
    /**
     * Apply search:field_name filters to the active query builder instance.
     *
     * @param mixed $query CodeIgniter active-record query builder (passed by reference)
     * @param array $search field_name => value
     * @param int $channel_id scopes the custom-field ChannelEntry lookup; pass 0 to skip scoping
     */
    protected function applySearchFilters($query, array $search, int $channel_id = 0): void
    {
        $core_columns = ee('export:EntryService')->getChannelTitlesColumns();
        $custom_filters = [];

        foreach ($search as $field_name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (isset($core_columns[$field_name])) {
                $query->where('channel_titles.' . $field_name, $value);
                continue;
            }

            foreach ($this->channel_fields as $field_id => $field_info) {
                if ($field_info['field_name'] === $field_name) {
                    $custom_filters[$field_id] = $value;
                    break;
                }
            }
        }

        if (empty($custom_filters)) {
            return;
        }

        $model_query = ee('Model')->get('ChannelEntry')->fields('entry_id');
        if ($channel_id) {
            $model_query->filter('channel_id', $channel_id);
        }
        foreach ($custom_filters as $field_id => $value) {
            $model_query->filter('field_id_' . $field_id, $value);
        }

        // Collection::pluck() already returns a plain PHP array (it's built on
        // array_map() internally) — not another Collection.
        $matching_ids = $model_query->all()->pluck('entry_id');

        if (empty($matching_ids)) {
            // No entries match the custom-field filters — force zero rows
            // rather than silently falling back to the rest of the unfiltered set.
            $query->where('channel_titles.entry_id', -1);
            return;
        }

        $query->where_in('channel_titles.entry_id', array_unique($matching_ids));
    }
}
