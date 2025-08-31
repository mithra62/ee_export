<?php

namespace Mithra62\Export\Services;

use CI_DB_result;

class MemberService extends AbstractService
{
    /**
     * @var array
     */
    protected array $fields = [];

    /**
     * @var array
     */
    protected array $columns = [];

    /**
     * @return array
     */
    public function getFields(): array
    {
        if (!$this->fields) {
            $fields = ee('Model')
                ->get('MemberField');

            foreach ($fields->all() as $field) {
                $this->fields[$field->m_field_name] = $field;
            }
        }

        return $this->fields;
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        if (!$this->columns) {
            $query = ee()->db->query("SHOW COLUMNS FROM exp_members");
            if ($query instanceof CI_DB_result) {
                foreach ($query->result_array() as $row) {
                    $this->columns[$row['Field']] = $row['Field'];
                }
            }
        }

        return $this->columns;
    }
}