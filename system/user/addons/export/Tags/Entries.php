<?php

namespace Mithra62\Export\Tags;

class Entries extends AbstractTag
{
    public function process()
    {
        $params = $this->params();

        $params['source']                             = 'entries';
        $params['source:channel']                     = $this->param('channel');
        $params['source:status']                      = $this->param('status', 'open');
        $params['source:author_id']                   = $this->param('author_id');
        $params['source:limit']                       = $this->param('limit');
        $params['source:offset']                      = $this->param('offset', 0);
        $params['source:chunk_size']                  = $this->param('chunk_size', 500);
        $params['source:relationship_fields']         = $this->param('relationship_fields', 'title');

        $this->compile($params);
    }
}
