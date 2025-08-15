<?php
namespace Mithra62\Export\Sources\Source;

use Mithra62\Export\Sources\AbstractSource;
use CI_DB_result;
use Mithra62\Export\Exceptions\Sources\NoDataException;

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
    public function compile(): string
    {
        $query = ee()->db->query($this->getOption('query'));
        if($query instanceof CI_DB_result && $query->num_rows() > 0) {
            $results = $query->result_array();
            $this->writeCache($results);
            return $this->getCachePath();
        }

        throw new NoDataException("Nothing to export from your query");
    }
}