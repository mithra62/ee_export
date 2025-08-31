<?php
namespace Mithra62\Export\Modifiers;

use Mithra62\Export\Plugins\AbstractModifier;

class EeDate extends AbstractModifier
{
    /**
     * @var array|string[]
     */
    protected array $params = [
        'format'
    ];

    public function process(mixed $value): mixed
    {
        $format = $this->getParam('format');
        if($format && $value) {
            $value = ee()->localize->format_date($format, $value);
        }

        return $value;
    }
}