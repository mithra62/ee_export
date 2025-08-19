<?php
namespace Mithra62\Export\Plugins;

use Mithra62\Export\Exceptions\Sources\NoDataException;

abstract class AbstractDestination extends AbstractPlugin
{

    /**
     * @param string $finished_export
     * @return bool|int
     */
    abstract public function process(string $finished_export): bool|int;
}