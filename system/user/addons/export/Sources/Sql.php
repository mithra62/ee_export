<?php
namespace Mithra62\Export\Sources;

use CI_DB_result;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Sql extends AbstractSource
{
    /**
     * @var array|string[]
     */
    public array $rules = [
        'source' => 'required',
        'query' => 'required',
    ];

    /**
     * @return string
     * @throws NoDataException
     * @throws \Exception
     */
    public function compile(): AbstractSource
    {
        $query = ee()->db->query($this->getOption('query'));
        if($query instanceof CI_DB_result && $query->num_rows() > 0) {
            $results = $this->postProcess($query->result_array());
            $this->setExportData($results);
            return $this;
        }

        throw new NoDataException("Nothing to export from your query");
    }
}