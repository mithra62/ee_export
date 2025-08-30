<?php
namespace Mithra62\Export\Plugins;

abstract class AbstractFormat extends AbstractPlugin
{

    /**
     * @param AbstractSource $source
     * @return string
     */
    abstract public function compile(AbstractSource $source): string;
}
