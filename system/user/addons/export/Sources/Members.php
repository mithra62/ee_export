<?php

namespace Mithra62\Export\Sources;

use ExpressionEngine\Model\Member\Member as MemberModel;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Members extends AbstractSource
{

    /**
     * @return AbstractSource
     * @throws NoDataException
     */
    public function compile(): AbstractSource
    {
        $members = ee('Model')
            ->get('Member');

        if ($this->getOption('roles')) {
            $members->filter('role_id', 'IN', $this->getOption('roles'));
        }

        if ($this->getOption('join_start') && $this->getOption('join_end')) {
            $members->filter('join_date', '>=', strtotime($this->getOption('join_start')));
            $members->filter('join_date', '<=', strtotime($this->getOption('join_end')));
        }

        if ($this->getOption('last_login_start') && $this->getOption('last_login_end')) {
            $members->filter('last_visit', '>=', $this->getOption('last_login_start'));
            $members->filter('last_visit', '<=', $this->getOption('last_login_end'));
        }

        if ($this->getOption('search', [])) {
            $map = $this->buildFieldMap($this->getOption('search'));
            foreach ($map as $field => $search) {
                $members->filter($field, $search);
            }
        }

        if ($this->getOption('limit')) {
            $members->limit($this->getOption('limit'));
        }

        if ($members->count() > 0) {
            $results = [];
            foreach ($members->all() as $member) {
                $results[] = $this->prepareData($member);
            }

            $this->setExportData($results);
            return $this;
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
        if ($cols) {
            foreach ($cols as $col) {
                if (array_key_exists($col, $search) && !empty($search[$col])) {
                    $return[$col] = $search[$col];
                }
            }
        }

        $fields = ee('export:MemberService')->getFields();
        if ($fields) {
            foreach ($fields as $field) {
                if (array_key_exists($field->m_field_name, $search)) {
                    $return['m_field_id_' . $field->m_field_id] = $search[$field->m_field_name];
                }
            }
        }

        return $return;
    }

    /**
     * Build an export row from a Member model instance.
     *
     * Standard member columns are passed through as-is (arrays are JSON-encoded).
     * Custom member fields (m_field_id_X) are routed through FieldsService so
     * the same field handlers used by the Entries source apply here too — date
     * fields become timestamps, file fields become URLs, etc.
     */
    protected function prepareData(MemberModel $member): array
    {
        $return    = [];
        $fields    = ee('export:MemberService')->getFields();
        $member_id = (int) $member->member_id;

        foreach ($member->toArray() as $key => $value) {
            if (!str_starts_with($key, 'm_field_id_') && !str_starts_with($key, 'm_field_ft_')) {
                // Standard member column — pass through; JSON-encode any array values
                $return[$key] = is_array($value) ? json_encode($value) : $value;
            } else {
                // Custom member field — match to MemberField definition and process
                foreach ($fields as $field) {
                    if (str_starts_with($key, 'm_field_id_' . $field->m_field_id)) {
                        $return[$field->m_field_name] = $this->processFieldValue(
                            $value, $field, $member_id
                        );
                    }
                }
            }
        }

        return $this->cleanFields($return);
    }

    /**
     * Route a single member custom field value through the registered FieldsService
     * handler for its field type, falling back to the raw value when none exists.
     *
     * The field_info shape matches the Entries source contract:
     *   field_id, field_name, field_type, field_label, field_settings (decoded array)
     *
     * Context includes source_type = 'member' and member_id so third-party handlers
     * can distinguish this call-site from an Entries or Grid context.
     */
    protected function processFieldValue(mixed $raw_value, object $field, int $member_id): mixed
    {
        $settings = $field->m_field_settings ?? [];
        if (is_string($settings)) {
            $settings = @unserialize($settings) ?: [];
        }

        $field_info = [
            'field_id'       => (int) $field->m_field_id,
            'field_name'     => $field->m_field_name,
            'field_type'     => $field->m_field_type,
            'field_label'    => $field->m_field_label,
            'field_settings' => is_array($settings) ? $settings : [],
        ];

        $handler = ee('export:FieldsService')->getField($field->m_field_type);

        if ($handler) {
            return $handler->process($raw_value, $field_info, $member_id, [
                'source_type' => 'member',
                'member_id'   => $member_id,
            ]);
        }

        return $raw_value ?? '';
    }
}
