<?php
namespace Mithra62\Export\Plugins;

use Mithra62\Export\Exceptions\Sources\NoDataException;

abstract class AbstractSource extends AbstractPlugin
{
    /**
     * @return string
     * @throws NoDataException
     * @throws \Exception
     */
    abstract public function compile(): string;
}