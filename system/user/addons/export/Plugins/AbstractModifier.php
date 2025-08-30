<?php
namespace Mithra62\Export\Plugins;

abstract class AbstractModifier extends AbstractPlugin
{
    /**
     * @param mixed $value
     * @return mixed
     */
    abstract public function process(mixed $value): mixed;
}