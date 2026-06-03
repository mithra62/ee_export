<?php
namespace Mithra62\Export\Sources;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Plugins\AbstractSource;

class Entries extends AbstractSource
{
    public function compile(): AbstractSource
    {
        throw new NoDataException("Entries export is not yet implemented");
    }
}