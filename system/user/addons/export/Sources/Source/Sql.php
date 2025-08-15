<?php
namespace Mithra62\Export\Sources\Source;

use Mithra62\Export\Sources\AbstractSource;

class Sql extends AbstractSource
{
    /**
     * @var array|string[]
     */
    public array $rules = [
        'source' => 'required',
        'query' => 'required',
    ];
}