<?php
namespace Mithra62\Export\Modifiers;

use Mithra62\Export\Plugins\AbstractModifier;

class ReplaceWith extends AbstractModifier
{
    /**
     * @var array|string[]
     */
    protected array $params = [
        'with'
    ];

    public function process(mixed $value): mixed
    {
        return $this->getParam('with', 'N/A');
    }
}