<?php
namespace Mithra62\Export\Plugins;

//use Mithra62\Export\Exceptions\Sources\NoDataException;

abstract class AbstractFormat extends AbstractPlugin
{
    /**
     * @return string
     */
    abstract public function compile(): string;
}
