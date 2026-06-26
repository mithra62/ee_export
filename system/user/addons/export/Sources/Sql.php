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

    public function getCpFields(array $context = []): array
    {
        return [
            ['name' => 'query', 'type' => 'textarea', 'label' => 'export_field_sql'],
        ];
    }

    /**
     * @return AbstractSource
     * @throws NoDataException
     */
    public function compile(): AbstractSource
    {
        $query = ee()->db->query($this->getOption('query'));
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
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
            // Strip line comments (-- ...) and block comments (/* ... */) before analysis
            $clean = preg_replace('/--[^\n]*/', '', $value);
            $clean = preg_replace('/\/\*.*?\*\//s', '', $clean);
            $clean = trim($clean);

            // Must begin with SELECT
            if (!preg_match('/^select\s/i', $clean)) {
                return 'query must be a SELECT statement';
            }

            // Block multi-statement injection — semicolons allow stacking arbitrary SQL
            if (str_contains($clean, ';')) {
                return 'query must not contain semicolons';
            }

            // Belt-and-suspenders: block destructive keywords as whole words so a
            // comment-stripped payload like "SELECT 1 DROP TABLE foo" is rejected.
            $blocked = ['insert', 'update', 'delete', 'drop', 'truncate', 'alter',
                'create', 'replace', 'call', 'exec'];
            foreach ($blocked as $kw) {
                if (preg_match('/\b' . $kw . '\b/i', $clean)) {
                    return 'query contains disallowed keyword: ' . strtoupper($kw);
                }
            }

            return true;
        });

        return $validator;
    }
}