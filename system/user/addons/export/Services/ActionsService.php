<?php

namespace Mithra62\Export\Services;

use CI_DB_result;

class ActionsService extends AbstractService
{
    /**
     * @var string
     */
    protected string $addon_name = 'Hidden_files';

    /**
     * @var array
     */
    protected array $cache = [];


    /**
     * @param int $entry_id
     * @param int $field_id
     * @param int $row_id
     * @param string $col
     * @return string
     */
    public function getDownloadLink(int $entry_id, int $field_id, int $row_id = 0, string $col = ''): string
    {
        $action_id = $this->getActionId('Download');
        $url = '';
        if($action_id) {
            $url = ee()->config->item('site_url') . '?ACT=' . $action_id . '&e=' .
                $entry_id . '&f=' . $field_id;

            if($row_id >= 1 && $col != '') {
                $url .= '&c=' . $col . '&r=' . $row_id;
            }

        }

        return $url;
    }

    /**
     * @param int $file_id
     * @return string
     */
    public function getFileLink(int $file_id): string
    {
        $action_id = $this->getActionId('Download');
        $url = '';
        if($action_id) {
            $url = ee()->config->item('site_url') . '?ACT=' . $action_id . '&fi=' . $file_id;
        }

        return $url;
    }

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
        if($query instanceof CI_DB_result) {
            $return = $query->row()->action_id;
        }

        return $return;
    }
}