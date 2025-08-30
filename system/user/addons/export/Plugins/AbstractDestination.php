<?php
namespace Mithra62\Export\Plugins;

use Mithra62\Export\Exceptions\Sources\NoDataException;

abstract class AbstractDestination extends AbstractPlugin
{
    /**
     * Whether the execution should be closed upon success
     * @var bool
     */
    protected bool $force_exit = false;

    /**
     * @param string $finished_export
     * @return bool|int
     */
    abstract public function process(string $finished_export): bool|int;

    /**
     * @return bool
     */
    public function shouldDie(): bool
    {
        return $this->force_exit;
    }
}