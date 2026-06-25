<?php

namespace Mithra62\Export\Output;

use ExpressionEngine\Service\Validation\Validator;
use Mithra62\Export\Plugins\AbstractDestination;

class Local extends AbstractDestination
{
    /**
     * @var array|string[]
     */
    public array $rules = [
        'filename' => 'required',
        'path' => 'required|dirExists|dirWritable',
    ];

    public function getCpFields(array $context = []): array
    {
        return [
            ['name' => 'path', 'type' => 'text', 'label' => 'export_field_path', 'desc' => 'export_field_path_desc'],
        ];
    }

    /**
     * @param string $finished_export
     * @return bool|int
     */
    public function process(string $finished_export): bool|int
    {
        // basename() strips any directory components from the filename so that
        // a stored value like '../../config/database.php' cannot traverse outside
        // the approved destination directory.
        $filename = basename((string)$this->getOption('filename'));
        $path = rtrim($this->getOption('path'), '/\\') . DIRECTORY_SEPARATOR . $filename;
        return copy($finished_export, $path);
    }

    /**
     * @return Validator
     */
    protected function getValidator(): Validator
    {
        $validator = parent::getValidator();
        $data = $this->data;
        $validator->defineRule('dirExists', function ($key, $value, $parameters, $rule) use ($data) {
            return is_dir($value) ? true : 'dir not exist';
        });

        $validator->defineRule('dirWritable', function ($key, $value, $parameters, $rule) use ($data) {
            return is_writable($value) ? true : 'dir not writable';
        });

        return $validator;
    }
}