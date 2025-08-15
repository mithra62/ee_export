<?php
namespace Mithra62\Export\Sources;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractPlugin;

abstract class AbstractSource extends AbstractPlugin
{
    /**
     * @return string
     * @throws NoDataException
     * @throws \Exception
     */
    abstract public function compile(): string;
}