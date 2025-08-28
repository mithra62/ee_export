<?php
namespace Mithra62\Export\Services;

use EE_Fieldtype;

class FieldTypesService
{
    /**
     * @var array
     */
    protected array $fts = [];

    /**
     * @var array|string[]
     */
    protected array $allowed_compatibility = [
        'text', 'list', 'date', 'file',
    ];

    /**
     * @param string $field_type
     * @return array
     */
    public function getField(string $field_type): array
    {
        $fields = $this->getFieldTypes();
        return $fields[$field_type] ?? [];
    }

    /**
     * @return array
     */
    public function getFieldTypes(): array
    {
        if (!$this->fts) {
            $providers = ee('App')->getProviders();
            $fields = ee()->api_channel_fields->fetch_installed_fieldtypes();
            foreach ($providers as $providerKey => $provider) {
                if ($provider->get('fieldtypes')) {
                    $ft = $provider->get('fieldtypes');
                    foreach ($ft as $k => $v) {
                        if($k == 'highlander_ft') {
                            continue;
                        }
                        if (!empty($v['compatibility']) && in_array($v['compatibility'], $this->allowed_compatibility)) {
                            $ft[$k]['field_type'] = $k;
                            $ft[$k]['member'] = (!empty($v['use']) &&
                                is_array($v['use']) &&
                                in_array('MemberField', $v['use']));
                            if (isset($fields[$k])) {
                                $ft = array_merge($ft[$k], $fields[$k]);
                                $this->fts[$k] = $ft;
                                break;
                            }
                        }
                    }
                }
            }

            ksort($this->fts);
        }

        return $this->fts;
    }

    /**
     * @param string $type
     * @param $data
     * @param $settings
     * @param $ft_name
     * @return EE_Fieldtype|null
     */
    public function build(string $type, $data = '', $settings = [], $ft_name = ''): ?EE_Fieldtype
    {
        $fields = $this->getFieldTypes();
        $obj = null;
        if (!empty($fields[$type])) {
            $class = '\\' . $fields[$type]['class'];
            if (class_exists($class)) {
                $obj = new $class;
                if ($obj instanceof EE_Fieldtype) {

                    $config = [
                        'field_id' => '',
                        'field_name' => $ft_name,
                        'settings' => $settings,
                    ];
                    $obj->_init($config);
                }
            }
        }

        return $obj;
    }

    /**
     * @param array $settings
     * @return array
     */
    public function getDisplaySettings(array $settings): array
    {
        $return = [];
        $fields = $this->getFieldTypes();
        foreach ($fields as $type => $field) {
            $obj = $this->build($field['field_type']);
            $form = $obj->display_settings($settings);
            if($form) {
                foreach($form AS $key => $value) {
                    $return[$field['field_type']] = $value;
                    $return[$field['field_type']]['group'] = 'hl_' . $value['group'];
                }
            } else {
                $return[$field['field_type']]['group'] = 'hl_' . $field['field_type'];
            }
        }

        return $return;
    }

    /**
     * @param string $field
     * @return string
     */
    public function getFieldCompatibility(string $field): string
    {
        $return = '';
        $field = $this->getField($field);
        if($field) {
            $return = $field['field_type'];
        }

        return $return;
    }
}