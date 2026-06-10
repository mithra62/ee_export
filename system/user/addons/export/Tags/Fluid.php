<?php

namespace Mithra62\Export\Tags;

/**
 * {exp:export:fluid} — exports every instance of an EE Fluid field as a flat table.
 *
 * Each exported row represents one fluid field instance and includes entry-level
 * context columns (entry_id, entry_title) alongside the instance metadata
 * (instance_order, sub_field_type, sub_field_label) and the processed value.
 *
 * Required params:
 *   channel="channel_short_name"   Channel whose entries to query
 *   field="fluid_field_name"       Fluid field name (or numeric field_id)
 *
 * Optional params:
 *   status="open"                  Entry status filter (default: open)
 *   author_id="5"                  Filter entries by member ID
 *   entry_id="42"                  Export fluid instances for a single entry only
 *   limit="100"                    Max number of *entries* to process. Each entry can have
 *                                  many fluid instances, so the total output row count may be
 *                                  much higher than this value.
 *   offset="0"                     Entry-level pagination offset
 *   chunk_size="500"               Entries per streaming chunk (default: 500)
 *   fields="col1|col2"             Whitelist — return only these output columns
 *   exclude="col1|col2"            Blacklist — remove these output columns
 *
 * Example:
 *   {exp:export:fluid
 *       channel="blog"
 *       field="page_builder"
 *       status="open"
 *       format="csv"
 *       output="download"
 *       output:filename="page-builder-instances.csv"
 *   }
 */
class Fluid extends AbstractTag
{
    public function process()
    {
        $params = $this->params();

        $params['source'] = 'fluid';
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
