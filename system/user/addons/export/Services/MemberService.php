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
     * Actual columns present in exp_member_data, keyed by column name.
     * Populated lazily by getMemberDataColumns().
     *
     * @var array|null  null = not yet fetched; [] = table empty or missing
     */
    protected ?array $member_data_columns = null;

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
     * Return the actual columns present in exp_member_data, keyed by column name.
     *
     * Used to guard against selecting m_field_id_X columns that don't exist in
     * the table. Field records in exp_member_fields can outpace the schema when
     * fields are created outside the normal EE flow, or when the table is restored
     * from a backup that pre-dates certain fields. Selecting a missing column
     * produces a fatal SQL error, so we validate before building the SELECT list.
     *
     * Result is cached for the lifetime of the service instance.
     *
     * @return array<string, string>  [column_name => column_name]
     */
    public function getMemberDataColumns(): array
    {
        if ($this->member_data_columns === null) {
            $this->member_data_columns = [];
            $query = ee()->db->query(
                'SHOW COLUMNS FROM ' . ee()->db->dbprefix . 'member_data'
            );
            if ($query instanceof CI_DB_result) {
                foreach ($query->result_array() as $row) {
                    $this->member_data_columns[$row['Field']] = $row['Field'];
                }
            }
        }

        return $this->member_data_columns;
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        if (!$this->columns) {
            $query = ee()->db->query("SHOW COLUMNS FROM " . ee()->db->dbprefix . "members");
            if ($query instanceof CI_DB_result) {
                foreach ($query->result_array() as $row) {
                    $this->columns[$row['Field']] = $row['Field'];
                }
            }
        }

        return $this->columns;
    }
}