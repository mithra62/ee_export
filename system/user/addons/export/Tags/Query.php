<?php

namespace Mithra62\Export\Tags;

use Mithra62\Export\Exceptions\Exception;

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

                try {
                    $export->build()->out();
                } catch(Exception $e) {
                    show_error($e->getMessage());
                }

            } else {
                show_error(print_r($export->getErrors(), true));
            }
        }
    }
}
