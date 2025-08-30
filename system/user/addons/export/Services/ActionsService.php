<?php

namespace Mithra62\Export\Services;

use CI_DB_result;

class ActionsService extends AbstractService
{
    /**
     * @var string
     */
    protected string $addon_name = 'Export';

    /**
     * @var array
     */
    protected array $cache = [];

    /**
     * @param string $action
     * @param string $class
     * @return int
     */
    public function getActionId(string $action, string $class = ''): int
    {
        if (!$class) {
            $class = $this->addon_name;
        }

        $where = [
            'method' => $action,
            'class' => $class,
        ];

        $return = 0;
        $query = ee()->db->select('action_id')->from('actions')->where($where)->get();
        if ($query instanceof CI_DB_result) {
            $return = $query->row()->action_id;
        }

        return $return;
    }
}