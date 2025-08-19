<?php

namespace Mithra62\Export\Output;

use Mithra62\Export\Plugins\AbstractDestination;

class Local extends AbstractDestination
{
    /**
     * @var array|string[]
     */
    public array $rules = [
        'filename' => 'required',
        'path' => 'required|writable|file_exists',
    ];

    /**
     * @param string $finished_export
     * @return bool|int
     */
    public function process(string $finished_export): bool|int
    {
        return copy($finished_export, $this->getOption('path') . '/' . $this->getOption('filename'));
    }
}