<?php

namespace Mithra62\Export\Sources;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;
use ExpressionEngine\Model\Member\Member AS MemberModel;
use CI_DB_result;

class Members extends AbstractSource
{
    public function compile(): string
    {
        $members = ee('Model')
            ->get('Member');

        if ($this->getOption('roles')) {
            $members->filter('role_id', 'IN', $this->getOption('roles'));
        }

        if ($this->getOption('join_start') && $this->getOption('join_end')) {
            $members->filter('join_date', '>=', $this->getOption('last_login_start'));
            $members->filter('join_date', '<=', $this->getOption('last_login_end'));
        }

        if ($this->getOption('last_login_start') && $this->getOption('last_login_end')) {
            $members->filter('last_visit', '>=', $this->getOption('last_login_start'));
            $members->filter('last_visit', '<=', $this->getOption('last_login_end'));
        }

        if ($this->getOption('search')) {
            $map = $this->buildFieldMap($this->getOption('search'));
            foreach ($map as $field => $search) {
                $members->filter($field, $search);
            }
        }

        if($this->getOption('limit')) {
            $members->limit($this->getOption('limit'));
        }

        if ($members->count() > 0) {
            $results = [];
            foreach($members->all() AS $member) {
                $results[] = $this->prepareData($member);
                print_r($results);
                exit;
            }

            $this->writeCache($results);
            return $this->getCachePath();
        }

        throw new NoDataException("Nothing to export from your query");
    }

    /**
     * @param array $search
     * @return array
     */
    protected function buildFieldMap(array $search): array
    {
        $return = [];
        $cols = ee('export:MemberService')->getColumns();
        if($cols) {
            foreach ($cols as $col) {
                if(array_key_exists($col, $search) && !empty($search[$col])) {
                    $return[$col] = $search[$col];
                }
            }
        }

        $fields = ee('export:MemberService')->getFields();
        if ($fields) {
            foreach ($fields as $field) {
                if(array_key_exists($field->m_field_name, $search)) {
                    $return['m_field_id_' . $field->m_field_id] = $search[$field->m_field_name];
                }
            }
        }

        return $return;
    }

    /**
     * @param MemberModel $member
     * @return array
     */
    protected function prepareData(MemberModel $member): array
    {
        $return = [];
        $fields = ee('export:MemberService')->getFields();
        foreach($member->toArray() AS $key => $value) {
            if(!str_starts_with($key, 'm_field_id_') && !str_starts_with($key, 'm_field_ft_')) {
                if(is_array($value)) {
                    $return[$key] = json_encode($value);
                } else {
                    $return[$key] = $value;
                }
            } else {
                foreach($fields AS $field) {
                    if(str_starts_with($key, 'm_field_id_' . $field->m_field_id)) {
                        $return[$field->m_field_name] = $value;
                    }
                }
            }

        }

        return $return;
    }
}