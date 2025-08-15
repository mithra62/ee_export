<?php

namespace Mithra62\Export\Tags;

class Query extends AbstractTag
{
    public function process()
    {
        $params = $this->params();
        $params['source'] = 'sql';
        $params['source:query'] = $this->param('sql');
        $export = ee('export:ExportService')->setParameters($params);

        if($export->validate()) {
            $export->build()->output();
        }
    }
}
