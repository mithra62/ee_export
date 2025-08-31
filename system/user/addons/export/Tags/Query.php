<?php

namespace Mithra62\Export\Tags;

class Query extends AbstractTag
{
    /**
     * @return void
     */
    public function process()
    {
        $params = $this->params();
        $params['source'] = 'sql';
        $params['source:query'] = $this->param('sql');
        $this->compile($params);
    }
}
