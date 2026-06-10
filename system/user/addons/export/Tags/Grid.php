<?php

namespace Mithra62\Export\Tags;

/**
 * {exp:export:grid} — exports every row of an EE Grid field as a flat table.
 *
 * Each exported row represents one grid row and includes entry-level context
 * columns (entry_id, entry_title, row_order) alongside the grid column values.
 *
 * Required params:
 *   channel="channel_short_name"   Channel whose entries to query
 *   field="grid_field_name"        Grid field name (or numeric field_id)
 *
 * Optional params:
 *   status="open"                  Entry status filter (default: open)
 *   author_id="5"                  Filter entries by member ID
 *   entry_id="42"                  Export grid rows for a single entry only
 *   limit="100"                    Max number of *entries* to process. Each entry can have
 *                                  many grid rows, so the total output row count may be much
 *                                  higher than this value.
 *   offset="0"                     Entry-level pagination offset
 *   chunk_size="500"               Entries per streaming chunk (default: 500)
 *   relationship_fields="title"    Fields to pull from relationship-column targets (pipe-separated)
 *   fields="col1|col2"             Whitelist — return only these output columns, in this order
 *   exclude="col1|col2"            Blacklist — remove these output columns, return the rest
 *
 * Example:
 *   {exp:export:grid
 *       channel="products"
 *       field="variants"
 *       status="open"
 *       format="csv"
 *       output="download"
 *       output:filename="variants.csv"
 *   }
 */
class Grid extends AbstractTag
{
    public function process()
    {
        $params = $this->params();

        $params['source'] = 'grid';
        $params['source:channel'] = $this->param('channel');
        $params['source:field'] = $this->param('field');
        $params['source:status'] = $this->param('status', 'open');
        $params['source:author_id'] = $this->param('author_id');
        $params['source:entry_id'] = $this->param('entry_id');
        $params['source:limit'] = $this->param('limit');
        $params['source:offset'] = $this->param('offset', 0);
        $params['source:chunk_size'] = $this->param('chunk_size', 500);
        $params['source:relationship_fields'] = $this->param('relationship_fields', 'title');

        $this->compile($params);
    }
}
