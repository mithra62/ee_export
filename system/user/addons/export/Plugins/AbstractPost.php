<?php
namespace Mithra62\Export\Plugins;

abstract class AbstractPost extends AbstractPlugin
{
    /**
     * @return string
     */
    abstract public function process(): string;
}