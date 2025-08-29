<?php
namespace Mithra62\Export\Plugins;

//use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

abstract class AbstractFormat extends AbstractPlugin
{
    /**
     * @return string
     */
    abstract public function compile(AbstractSource $source): string;
}
