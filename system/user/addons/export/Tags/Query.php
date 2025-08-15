<?php

namespace Mithra62\Export\Tags;

class Query extends AbstractTag
{
    public function process()
    {
        if ($this->param('format') && $this->param('output')) {
            $params = $this->params();
            $params['source'] = 'sql';
            $params['source:query'] = $this->param('sql');
            $export = ee('export:ExportService')->setParameters($params);

            if ($export->validate()) {
                $export->build()->output();
            } else {
                echo 'errors';
                exit;
            }
        }
    }
}
