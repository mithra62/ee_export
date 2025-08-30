<?php
namespace Mithra62\Export\Modifiers;

use Mithra62\Export\Plugins\AbstractModifier;

class EeDecrypt extends AbstractModifier
{
    public function process(mixed $value): mixed
    {
        return $value;
        return ee('Encrypt')->decrypt($value);
    }
}