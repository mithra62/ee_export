<?php
namespace Mithra62\Export\Modifiers;

use Mithra62\Export\Plugins\AbstractModifier;

class UcWords extends AbstractModifier
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function process(mixed $value): mixed
    {
        return ucwords($value);
    }
}