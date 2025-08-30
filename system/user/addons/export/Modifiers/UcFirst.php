<?php
namespace Mithra62\Export\Modifiers;

use Mithra62\Export\Plugins\AbstractModifier;

class UcFirst extends AbstractPost
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function process(mixed $value): mixed
    {
        return ucfirst($value);
    }
}