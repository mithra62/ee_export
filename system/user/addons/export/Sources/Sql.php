<?php
namespace Mithra62\Export\Sources;

use CI_DB_result;
use ExpressionEngine\Service\Validation\Validator;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Sql extends AbstractSource
{
    /**
     * @var array|string[]
     */
    public array $rules = [
        'source' => 'required',
        'query' => 'required|isSelect',
    ];

    /**
     * @return AbstractSource
     * @throws NoDataException
     */
    public function compile(): AbstractSource
    {
        $query = ee()->db->query($this->getOption('query'));
        if($query instanceof CI_DB_result && $query->num_rows() > 0) {
            $this->setExportData($query->result_array());
            return $this;
        }

        throw new NoDataException("Nothing to export from your query");
    }

    /**
     * @return Validator
     */
    protected function getValidator(): Validator
    {
        $validator = parent::getValidator();
        $data = $this->data;
        $validator->defineRule('isSelect', function ($key, $value, $parameters, $rule) use ($data) {
            return str_starts_with(strtolower($value), 'select') ? true : 'invalid query';
            //return ($data['mode'] == $parameters[0]) ? true : $rule->skip();
        });

        return $validator;
    }
}