<?php

namespace Mithra62\Export\Tags;

class Query extends AbstractTag
{
    public function process()
    {
        $params = $this->params();
        $params['source'] = 'sql';
        $export = ee('export:ExportService')->setParameters($params);

        if($export->validate()) {
            $export->build();
        }

        print_r($this->params());
        exit;
        return "My tag";
    }
}
