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

    /**
     * @param string $finished_export
     * @return bool|int
     */
    public function process(string $finished_export): bool|int
    {
        $path = rtrim($this->getOption('path'), '/\\') . DIRECTORY_SEPARATOR . $this->getOption('filename');
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