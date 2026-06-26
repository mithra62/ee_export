<?php

namespace Mithra62\Export\Services;

use CI_DB_result;
use ExpressionEngine\Model\File\File as FileModel;
use ExpressionEngine\Service\Validation\Validator;

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
     * Actual columns present in exp_channel_titles, keyed by column name.
     * Populated lazily by getChannelTitlesColumns().
     *
     * @var array|null  null = not yet fetched; [] = table empty or missing
     */
    protected ?array $channel_titles_columns = null;

    /**
     * Return the actual columns present in exp_channel_titles, keyed by column name.
     *
     * Used by SearchFilterTrait to guard against searching a column name that
     * doesn't actually exist on channel_titles, the same defense
     * MemberService::getMemberDataColumns() provides for Members. Result is
     * cached for the lifetime of the service instance.
     *
     * @return array<string, string>  [column_name => column_name]
     */
    public function getChannelTitlesColumns(): array
    {
        if ($this->channel_titles_columns === null) {
            $this->channel_titles_columns = [];
            $query = ee()->db->query(
                'SHOW COLUMNS FROM ' . ee()->db->dbprefix . 'channel_titles'
            );
            if ($query instanceof CI_DB_result) {
                foreach ($query->result_array() as $row) {
                    $this->channel_titles_columns[$row['Field']] = $row['Field'];
                }
            }
        }

        return $this->channel_titles_columns;
    }

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

        if ($group) {
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
                        $return = (string)($result[$key] ?? '');
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
     * Resolve a channel short name or numeric ID string to a channel_id.
     */
    public function getChannelId(string $channel_name): int
    {
        // Try by short name first
        $query = ee()->db->select('channel_id')
            ->from('channels')
            ->where('channel_name', $channel_name)
            ->limit(1)
            ->get();

        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            return (int)$query->row('channel_id');
        }

        // Fall back to numeric ID
        if (is_numeric($channel_name)) {
            $query = ee()->db->select('channel_id')
                ->from('channels')
                ->where('channel_id', (int)$channel_name)
                ->limit(1)
                ->get();

            if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
                return (int)$query->row('channel_id');
            }
        }

        return 0;
    }

    /**
     * Returns field definitions for a channel keyed by field_id.
     * Each entry: [field_id, field_name, field_type, field_label, field_settings (decoded)]
     */
    public function getChannelFields(int $channel_id): array
    {
        $return = [];

        // Tier 1: EE ORM via getAllCustomFields() — merges BOTH direct field
        // assignments (channels_channel_fields) AND field-group assignments
        // (channels_channel_field_groups → ChannelFields).  This is EE's own
        // authoritative method for "what custom fields does this channel have".
        try {
            $channel = ee('Model')
                ->get('Channel')
                ->filter('channel_id', $channel_id)
                ->first();

            if ($channel) {
                $fields = $channel->getAllCustomFields();
                if ($fields && count($fields) > 0) {
                    foreach ($fields as $field) {
                        $settings = $field->field_settings;
                        if (is_string($settings)) {
                            $settings = @unserialize(base64_decode($settings)) ?: [];
                        }
                        $return[(int)$field->field_id] = [
                            'field_id' => (int)$field->field_id,
                            'field_name' => $field->field_name,
                            'field_type' => $field->field_type,
                            'field_label' => $field->field_label,
                            'field_settings' => is_array($settings) ? $settings : [],
                        ];
                    }

                    return $return;
                }
            }
        } catch (\Throwable $e) {
            // fall through to raw-query approach
        }

        // Tier 2: raw SQL — direct assignments via channels_channel_fields (EE 5.4+).
        // No table aliases — avoids CI query-builder prefix mangling.
        $q2 = ee()->db
            ->select('channel_fields.field_id, channel_fields.field_name, channel_fields.field_type, channel_fields.field_label, channel_fields.field_settings')
            ->from('channel_fields')
            ->join('channels_channel_fields', 'channel_fields.field_id = channels_channel_fields.field_id')
            ->where('channels_channel_fields.channel_id', $channel_id)
            ->order_by('channel_fields.field_order', 'ASC')
            ->get();

        if ($q2 instanceof CI_DB_result && $q2->num_rows() > 0) {
            foreach ($q2->result_array() as $row) {
                $row['field_settings'] = $row['field_settings']
                    ? @unserialize(base64_decode($row['field_settings'])) ?: []
                    : [];
                $return[(int)$row['field_id']] = $row;
            }

            return $return;
        }

        // Tier 3: field-group fallback via channels_channel_field_groups pivot (EE 6+).
        // channels_channel_field_groups maps channel_id → group_id; channel_fields
        // rows carry the matching group_id.
        $gq = ee()->db
            ->select('group_id')
            ->from('channels_channel_field_groups')
            ->where('channel_id', $channel_id)
            ->get();

        if (!($gq instanceof CI_DB_result) || $gq->num_rows() === 0) {
            return $return;
        }

        $group_ids = array_column($gq->result_array(), 'group_id');
        $group_ids = array_map('intval', array_filter($group_ids));

        if (empty($group_ids)) {
            return $return;
        }

        $q3 = ee()->db
            ->select('field_id, field_name, field_type, field_label, field_settings')
            ->from('channel_fields')
            ->where_in('group_id', $group_ids)
            ->order_by('field_order', 'ASC')
            ->get();

        if ($q3 instanceof CI_DB_result && $q3->num_rows() > 0) {
            foreach ($q3->result_array() as $row) {
                $row['field_settings'] = $row['field_settings']
                    ? @unserialize(base64_decode($row['field_settings'])) ?: []
                    : [];
                $return[(int)$row['field_id']] = $row;
            }
        }

        return $return;
    }

    /**
     * Returns column definitions for a grid field keyed by col_id.
     * Each entry: [col_id, col_name, col_label, col_type]
     */
    public function getGridColumns(int $field_id): array
    {
        $query = ee()->db
            ->select('col_id, col_name, col_label, col_type, col_settings')
            ->from('grid_columns')
            ->where('field_id', $field_id)
            ->order_by('col_order', 'ASC')
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row['col_settings'] = $row['col_settings']
                    ? @json_decode($row['col_settings'], true) ?: []
                    : [];
                $return[(int)$row['col_id']] = $row;
            }
        }

        return $return;
    }

    /**
     * Batch-load custom field values from channel_data for a set of entry IDs.
     * Returns [entry_id => [field_id_X => value, ...]]
     */
    public function batchFieldData(array $entry_ids): array
    {
        $query = ee()->db->from('channel_data')
            ->where_in('entry_id', $entry_ids)
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $return[(int)$row['entry_id']] = $row;
            }
        }

        return $return;
    }

    /**
     * Batch-load field values from EE 7 split-storage tables (channel_data_field_X).
     *
     * EE 7 can store each custom field in its own table instead of the shared
     * channel_data table. This queries each split table that exists and merges
     * the results into the same [entry_id => [field_id_X => value]] shape so
     * callers can treat the output identically to batchFieldData().
     *
     * Returns [entry_id => [field_id_X => value, ...]]
     */
    public function batchSplitFieldData(array $entry_ids, array $field_ids): array
    {
        $return = [];
        foreach ($field_ids as $field_id) {
            $table = 'channel_data_field_' . $field_id;
            if (!ee()->db->table_exists($table)) {
                continue;
            }
            $col   = 'field_id_' . $field_id;
            $query = ee()->db
                ->select('entry_id, ' . $col)
                ->from($table)
                ->where_in('entry_id', $entry_ids)
                ->get();
            if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
                foreach ($query->result_array() as $row) {
                    $return[(int)$row['entry_id']][$col] = $row[$col] ?? null;
                }
            }
        }
        return $return;
    }

    /**
     * Batch-load all grid rows for a field and a set of entry IDs.
     * Returns [entry_id => [row, row, ...]] ordered by row_order.
     * Only loads top-level grid rows (fluid_field_data_id = 0).
     */
    public function batchGridData(int $field_id, array $entry_ids): array
    {
        $table = 'channel_grid_field_' . $field_id;
        if (!ee()->db->table_exists($table)) {
            return [];
        }

        $query = ee()->db
            ->from($table)
            ->where_in('entry_id', $entry_ids)
            ->where('fluid_field_data_id', 0)
            ->order_by('entry_id')
            ->order_by('row_order')
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $return[(int)$row['entry_id']][] = $row;
            }
        }

        return $return;
    }

    /**
     * Batch-load relationship records for a set of entries and field IDs.
     * Returns [entry_id => [field_id => [child_entry_id, ...]]]
     */
    public function batchRelationshipIds(array $entry_ids, array $field_ids): array
    {
        $query = ee()->db
            ->select('parent_id, child_id, field_id')
            ->from('relationships')
            ->where_in('parent_id', $entry_ids)
            ->where_in('field_id', $field_ids)
            ->where('fluid_field_data_id', 0)
            ->order_by('order')
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $return[(int)$row['parent_id']][(int)$row['field_id']][] = (int)$row['child_id'];
            }
        }

        return $return;
    }

    /**
     * Resolve a set of entry IDs to their titles (and optionally other fields).
     * Returns [entry_id => ['title' => '...', ...]]
     * $rel_fields should be field_name values from channel_fields (not field_id_X column names).
     */
    public function resolveRelatedEntries(array $entry_ids, array $rel_fields = ['title']): array
    {
        if (empty($entry_ids)) {
            return [];
        }

        $select = 'ct.entry_id, ct.title';
        $extra = array_filter(array_diff($rel_fields, ['title']), fn($f) => preg_match('/^[a-z0-9_]+$/i', $f));
        if ($extra) {
            $select .= ', cd.' . implode(', cd.', $extra);
        }

        $query = ee()->db
            ->select($select)
            ->from('channel_titles ct')
            ->join('channel_data cd', 'ct.entry_id = cd.entry_id', 'left')
            ->where_in('ct.entry_id', $entry_ids)
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $return[(int)$row['entry_id']] = $row;
            }
        }

        return $return;
    }

    /**
     * Batch-load category names for a set of entry IDs.
     * Returns [entry_id => 'Cat One|Cat Two|Cat Three']
     */
    public function batchCategoryNames(array $entry_ids): array
    {
        $query = ee()->db
            ->select('cp.entry_id, c.cat_name')
            ->from('category_posts cp')
            ->join('categories c', 'cp.cat_id = c.cat_id')
            ->where_in('cp.entry_id', $entry_ids)
            ->order_by('c.cat_order', 'ASC')
            ->get();

        $raw = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $raw[(int)$row['entry_id']][] = $row['cat_name'];
            }
        }

        $return = [];
        foreach ($raw as $entry_id => $names) {
            $return[$entry_id] = implode('|', $names);
        }

        return $return;
    }

    /**
     * Batch-load all fluid_field_data rows for a set of entries and fluid field IDs.
     * Returns [entry_id][fluid_field_id][] = instance row
     * (each row includes: id, entry_id, fluid_field_id, field_id, field_data_id, order)
     */
    public function batchFluidInstances(array $entry_ids, array $fluid_field_ids): array
    {
        if (empty($entry_ids) || empty($fluid_field_ids)) {
            return [];
        }

        $query = ee()->db
            ->from('fluid_field_data')
            ->where_in('entry_id', $entry_ids)
            ->where_in('fluid_field_id', $fluid_field_ids)
            ->order_by('entry_id')
            ->order_by('fluid_field_id')
            ->order_by('order', 'ASC')
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $return[(int)$row['entry_id']][(int)$row['fluid_field_id']][] = $row;
            }
        }

        return $return;
    }

    /**
     * Batch-load sub-field values from channel_data_field_X for a set of data IDs.
     * Returns [data_id => value]
     */
    public function batchFluidSubFieldValues(int $field_id, array $data_ids): array
    {
        if (empty($data_ids)) {
            return [];
        }

        $table = 'channel_data_field_' . $field_id;
        if (!ee()->db->table_exists($table)) {
            return [];
        }

        $col = 'field_id_' . $field_id;
        $query = ee()->db
            ->select('id, ' . $col)
            ->from($table)
            ->where_in('id', $data_ids)
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $return[(int)$row['id']] = $row[$col] ?? null;
            }
        }

        return $return;
    }

    /**
     * Batch-load grid rows nested inside fluid field instances.
     * Queries channel_grid_field_X using fluid_field_data_id (the fluid instance PK).
     * Returns [fluid_instance_id][] = grid row array
     */
    public function batchFluidGridData(int $field_id, array $fluid_instance_ids): array
    {
        if (empty($fluid_instance_ids)) {
            return [];
        }

        $table = 'channel_grid_field_' . $field_id;
        if (!ee()->db->table_exists($table)) {
            return [];
        }

        $query = ee()->db
            ->from($table)
            ->where_in('fluid_field_data_id', $fluid_instance_ids)
            ->order_by('fluid_field_data_id')
            ->order_by('row_order', 'ASC')
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $return[(int)$row['fluid_field_data_id']][] = $row;
            }
        }

        return $return;
    }

    /**
     * Batch-load relationship child IDs for relationship sub-fields inside Fluid field instances.
     *
     * EE stores Fluid relationship values in the `relationships` pivot table using
     * `fluid_field_data_id` = the fluid instance PK (fluid_field_data.id).
     * This is distinct from top-level relationship fields (fluid_field_data_id = 0)
     * and from Grid relationship columns (which store raw IDs in col_id_X cells).
     *
     * Returns [fluid_instance_id][field_id][] = child_entry_id, ordered by `order`.
     *
     * @param int[] $instance_ids fluid_field_data.id values to query
     * @param int[] $field_ids relationship field IDs (channel_fields.field_id)
     */
    public function batchFluidRelationshipIds(array $instance_ids, array $field_ids): array
    {
        if (empty($instance_ids) || empty($field_ids)) {
            return [];
        }

        $query = ee()->db
            ->select('fluid_field_data_id, child_id, field_id')
            ->from('relationships')
            ->where_in('fluid_field_data_id', $instance_ids)
            ->where_in('field_id', $field_ids)
            ->where('grid_field_id', 0)
            ->order_by('order', 'ASC')
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $return[(int)$row['fluid_field_data_id']][(int)$row['field_id']][] = (int)$row['child_id'];
            }
        }

        return $return;
    }

    /**
     * Resolve a grid field name (or numeric ID string) to a field_id,
     * validating that the field belongs to the given channel AND is of type 'grid'.
     *
     * Returns 0 when not found / wrong type.
     */
    public function getGridFieldId(string $field_name_or_id, int $channel_id): int
    {
        $channel_fields = $this->getChannelFields($channel_id);

        // Accept a numeric ID directly
        if (is_numeric($field_name_or_id)) {
            $fid = (int)$field_name_or_id;
            if (isset($channel_fields[$fid]) && $channel_fields[$fid]['field_type'] === 'grid') {
                return $fid;
            }
            return 0;
        }

        // Look up by field_name
        foreach ($channel_fields as $field_id => $field_info) {
            if ($field_info['field_name'] === $field_name_or_id && $field_info['field_type'] === 'grid') {
                return $field_id;
            }
        }

        return 0;
    }

    /**
     * Resolve a fluid field name (or numeric ID string) to a field_id,
     * validating that the field belongs to the given channel AND is of type 'fluid_field'.
     *
     * Returns 0 when not found / wrong type.
     */
    public function getFluidFieldId(string $field_name_or_id, int $channel_id): int
    {
        $channel_fields = $this->getChannelFields($channel_id);

        if (is_numeric($field_name_or_id)) {
            $fid = (int)$field_name_or_id;
            if (isset($channel_fields[$fid]) && $channel_fields[$fid]['field_type'] === 'fluid_field') {
                return $fid;
            }
            return 0;
        }

        foreach ($channel_fields as $field_id => $field_info) {
            if ($field_info['field_name'] === $field_name_or_id && $field_info['field_type'] === 'fluid_field') {
                return $field_id;
            }
        }

        return 0;
    }

    /**
     * Batch-load channel field definitions by an arbitrary set of field IDs.
     *
     * Returns [field_id => ['field_id', 'field_name', 'field_type', 'field_label', 'field_settings']]
     * Useful when the caller already knows the field_ids (e.g. from fluid_field_data rows) and
     * does not need the full channel-scoped field list.
     */
    public function getFieldDefinitions(array $field_ids): array
    {
        if (empty($field_ids)) {
            return [];
        }

        $query = ee()->db
            ->select('field_id, field_name, field_type, field_label, field_settings')
            ->from('channel_fields')
            ->where_in('field_id', $field_ids)
            ->get();

        $return = [];
        if ($query instanceof CI_DB_result && $query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $settings = $row['field_settings'];
                if (is_string($settings)) {
                    $settings = @unserialize(base64_decode($settings)) ?: [];
                }
                $row['field_settings'] = is_array($settings) ? $settings : [];
                $return[(int)$row['field_id']] = $row;
            }
        }

        return $return;
    }

    /**
     * Batch-load relationship child IDs stored directly in grid column cells.
     *
     * Grid relationship columns store raw entry_id integers in col_id_X cells
     * (not via the `relationships` pivot table). This helper collects all unique
     * non-zero values from those columns across a slice of grid rows and returns
     * a flat list suitable for passing to resolveRelatedEntries().
     *
     * @param array $grid_rows Flat row arrays from channel_grid_field_X
     * @param int[] $col_ids Column IDs whose values are entry_id references
     * @return int[]             Unique non-zero related entry_ids
     */
    public function collectGridRelatedIds(array $grid_rows, array $col_ids): array
    {
        $ids = [];
        foreach ($grid_rows as $row) {
            foreach ($col_ids as $col_id) {
                $val = (int)($row['col_id_' . $col_id] ?? 0);
                if ($val > 0) {
                    $ids[$val] = $val;
                }
            }
        }
        return array_values($ids);
    }

}