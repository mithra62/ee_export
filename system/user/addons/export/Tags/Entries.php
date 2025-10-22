<?php

namespace Mithra62\Export\Tags;

class Entries extends AbstractTag
{
    public function process()
    {
        $params = $this->params();

        $params['source'] = 'entries';
        $params['source:limit'] = $this->param('limit');
        $this->compile($params);
    }
}
