<?php
namespace Mithra62\Export\Output\Destination;

use Mithra62\Export\Output\AbstractDestination;

class Download extends AbstractDestination
{
    /**
     * @var array|string[]
     */
    public array $rules = [
        'filename' => 'required',
    ];
}