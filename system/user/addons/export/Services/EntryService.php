<?php

namespace Mithra62\Export\Services;

use CI_DB_result;
use ExpressionEngine\Model\File\File as FileModel;

class EntryService extends AbstractService
{
    /**
     * @var array
     */
    protected array $fluid_fields = [];

    /**
     * @var array
     */
    protected array $fluid_data = [];

    /**
     * @param int $field_id
     * @param int $entry_id
     * @param array $fields
     * @param int $fluid_field_data_id
     * @return array
     */
    public function getGridData(int $field_id, int $entry_id, array $fields, int $fluid_field_data_id = 0): array
    {
        $return = [];
        ee()->load->model('grid_model');
        $table = 'channel_grid_field_' . $field_id;
        ee()->db->where('entry_id', $entry_id);
        ee()->db->where('fluid_field_data_id', $fluid_field_data_id);
        ee()->db->order_by('row_order ASC');

        $grid_data = ee()->db->get($table)->result_array();
        if ($grid_data) {
            foreach ($grid_data as $row) {
                $var = $row;
                foreach ($fields as $key => $value) {
                    if (isset($row[$value])) {
                        $var[$key] = $row[$value];
                    }
                }

                if (count($var) >= 1) {
                    $return[] = $var;
                }
            }
        }

        return $return;
    }

    /**
     * This may not tbe the best way to do this but it does work
     * @param int $field_id
     * @param int $entry_id
     * @return int
     */
    protected function getGridFluidFieldId(int $field_id, int $entry_id): int
    {
        $return = 0;
        $table = 'channel_grid_field_' . $field_id;
        if (ee()->db->table_exists($table)) {
            $where = [
                'entry_id' => $entry_id,
            ];

            $query = ee()->db->select('fluid_field_data_id')->from($table)
                ->where($where)
                ->limit(1)
                ->get();

            if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
                $return = $query->row('fluid_field_data_id');
            }
        }

        return $return;
    }

    /**
     * @param int $entry_id
     * @param int|null $field_id
     * @return array
     */
    public function getFluidData(int $entry_id, ?int $field_id = null, $group = 0): array
    {
        $where = [
            'entry_id' => $entry_id,
        ];

        if ($field_id) {
            $where['fluid_field_id'] = $field_id;
        }

        if($group) {
            $where['group'] = $group;
        }

        $query = ee()->db->select()
            ->from('fluid_field_data')
            ->where($where)
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            $return = $query->result_array();
        }

        return $return;
    }


    /**
     * @param int $entry_id
     * @param int $fluid_field_id
     * @param int $field_id
     * @param int $group
     * @return string
     */
    public function getFluidFieldData(int $entry_id, int $fluid_field_id, int $field_id, int $group = 0): string
    {
        $return = '';
        $field_data = $this->getFluidData($entry_id, $fluid_field_id, $group);;

        foreach ($field_data as $row) {
            if ($row['field_id'] == $field_id) {
                $table = 'channel_data_field_' . $row['field_id'];
                if (ee()->db->table_exists($table)) {
                    $where = ['id' => $row['field_data_id']];
                    $query = ee()->db->select()->from($table)
                        ->where($where)
                        ->get();
                    $key = 'field_id_' . $row['field_id'];
                    $result = $query->row_array();
                    if (array_key_exists($key, $result)) {
                        $return = $result[$key];
                        break;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @param string|null $tag
     * @return string
     */
    public function getImageUrl(?string $tag): string
    {
        $url = '';

        if (!is_null($tag)) {
            $file_id = (int)filter_var($tag, FILTER_SANITIZE_NUMBER_INT);
            if ($file_id) {
                $file = ee('Model')
                    ->get('File')
                    ->filter('file_id', $file_id)
                    ->first();

                if ($file instanceof FileModel) {
                    if ($file->isImage()) {
                        $url = $file->getAbsoluteURL();
                        if (!$url) {
                            $url = '';
                        }
                    }
                }
            }
        }

        return $url;
    }

    /**
     * @param string $url_title
     * @param int $channel_id
     * @return int|null
     */
    protected function getEntryId(string $url_title, int $channel_id): ?int
    {
        $return = null;
        $where = [
            'url_title' => $url_title,
            'channel_id' => $channel_id,
        ];
        $data = ee()->db->select('entry_id')->from('channel_titles')->where($where)->get();
        if ($data instanceof CI_DB_result && $data->num_rows() > 0) {
            $return = $data->row('entry_id');
        }

        return $return;
    }


    /**
     * @param string $url_title
     * @return array
     */
    public function getNotifications(string $url_title, int $channel_id): array
    {
        $return = [];
        $entry_id = $this->getEntryId($url_title, $channel_id);
        if ($entry_id) {
            $return = $this->getGridData(215, $entry_id, ['copy' => 'col_id_116', 'type' => 'col_id_115']);
        }

        if ($return) {
            foreach ($return as $key => $value) {
                $return[$key]['type'] = strtolower($value['type']);
                $return[$key]['gid'] = $channel_id . '_' . $value['row_id'] . '-' . $url_title;
            }
        }

        return $return;
    }


    /**
     * @param string $url_title
     * @param int $group_id
     * @return int
     */
    public function getCatId(string $url_title, int $group_id): int
    {
        $return = 0;
        $query = ee()->db->select('cat_id')
            ->from('categories')
            ->where(['group_id' => $group_id, 'cat_url_title' => $url_title])
            ->get();

        if ($query instanceof CI_DB_result) {
            $return = ($query->row('cat_id') ? $query->row('cat_id') : 0);
        }

        return $return;
    }

    /**
     * @param string $market
     * @param $channel_id
     * @return array
     */
    public function getMarketChannelEntryIds(string $market, $channel_id): array
    {
        $return = [];
        $cat_id = $this->getCatId($market, 1);
        if ($cat_id) {
            $where = ['cat_id' => $cat_id, 'channel_id' => $channel_id, 'status' => 'open'];
            $query = ee()->db->select('channel_titles.entry_id')->from('category_posts')
                ->where($where)
                ->join('channel_titles', 'category_posts.entry_id = channel_titles.entry_id')
                ->order_by('entry_id', 'DESC');

            $query = $query->get();
            if ($query instanceof CI_DB_result) {
                foreach ($query->result_array() as $row) {
                    $return[] = $row['entry_id'];
                }
            }
        }

        return $return;
    }

    /**
     * @param $entry_ids
     * @param array $types
     * @return array
     */
    public function filterCategories($entry_ids, array $cats): array
    {
        $query = ee()->db->select()->from('category_posts')
            ->where_in('entry_id', $entry_ids)
            ->where_in('cat_id', $cats)
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result) {
            foreach ($query->result_array() as $row) {
                $return[] = $row['entry_id'];
            }
        }

        return $return;
    }

    /**
     * @param string $string
     * @param array $entries
     * @return array
     */
    public function filterString(string $string, array $entries): array
    {
        $return = [];
        foreach ($entries as $index => $entry) {
            foreach ($entry as $key => $value) {
                if(is_string($value)) {
                    if(strpos($value, $string) !== false) {
                        $return[] = $entry;
                    }
                } elseif(is_array($value)) {
                    foreach($value AS $k => $v) {
                        if(is_string($v)) {
                            if(strpos($v, $string) !== false) {
                                $return[] = $entry;
                            }
                        }
                    }
                }
            }
        }
        return $return;
    }

    /**
     * @param int $entry_id
     * @param int $group_id
     * @return array
     */
    public function getEntryCats(int $entry_id, int $group_id): array
    {
        $query = ee()->db->select()->from('categories')
            ->where(['group_id' => $group_id, 'category_posts.entry_id' => $entry_id, 'status' => 'open'])
            ->join('category_posts', 'category_posts.cat_id = categories.cat_id')
            ->join('channel_titles', 'category_posts.entry_id = channel_titles.entry_id')
            ->order_by('cat_order', 'ASC')
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result) {
            $return = $query->result_array();
        }

        return $return;
    }

    /**
     * @param int $entry_id
     * @param int $field_id
     * @param array $row
     * @return bool
     */
    public function createGridData(int $field_id, int $entry_id, array $row): bool
    {
        $table = 'channel_grid_field_' . $field_id;
        $row['entry_id'] = $entry_id;
        $row['row_order'] = 0;
        if(ee()->db->insert($table, $row)) {
            return true;
        }

        return false;
    }

    /**
     * @param int $entry_id
     * @param int $field_id
     * @param array $row
     * @return bool
     */
    public function updateGridData(int $row_id, int $field_id, array $row): bool
    {
        $table = 'channel_grid_field_' . $field_id;
        if(ee()->db->update($table, $row, ['row_id' => $row_id])) {
            return true;
        }

        return false;
    }

    /**
     * @param array $entry_ids
     * @return array
     */
    public function getEntriesCatIds(array $entry_ids): array
    {
        $query = ee()->db->select()->from('category_posts')
            ->where_in('entry_id', $entry_ids)
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result) {
            foreach ($query->result_array() as $row) {
                $return[$row['entry_id']][] = $row['cat_id'];
            }
        }

        return $return;
    }
}