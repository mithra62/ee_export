<?php

namespace Mithra62\Export\Traits;

/**
 * Shared `search:field_name="value"` filter logic for the Entries, Grid, and
 * Fluid sources. Each of those sources builds its own independent
 * channel_titles query in nextChunk() rather than delegating to a common
 * base, so this trait centralises the one piece of logic all three need
 * identically instead of tripling it.
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
     * Core channel_titles columns are matched directly. Custom field names are
     * resolved against $this->channel_fields and matched against channel_data,
     * joining that table only if at least one search key actually resolves to
     * a custom field.
     *
     * @param mixed $query CodeIgniter active-record query builder (passed by reference)
     * @param array $search field_name => value
     */
    protected function applySearchFilters($query, array $search): void
    {
        $core_columns = ee('export:EntryService')->getChannelTitlesColumns();
        $joined = false;

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
                    if (!$joined) {
                        $query->join('channel_data', 'channel_titles.entry_id = channel_data.entry_id', 'left');
                        $joined = true;
                    }
                    $query->where('channel_data.field_id_' . $field_id, $value);
                    break;
                }
            }
        }
    }
}
