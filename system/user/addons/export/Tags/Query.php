<?php

namespace Mithra62\Export\Tags;

class Query extends AbstractTag
{
    public function process()
    {
        $export = ee('export:ExportService')->setParams($this->params());
        if($export->validate()) {
            $export->build();
        }
        print_r($this->params());
        exit;
        return "My tag";
    }
}
