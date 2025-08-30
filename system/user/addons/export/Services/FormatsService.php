<?php

namespace Mithra62\Export\Services;

use ExpressionEngine\Library\String\Str;
use Mithra62\Export\Exceptions\Services\FormatsServiceException;
use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Traits\ParamsTrait;

class FormatsService extends AbstractService
{
    use ParamsTrait;

    /**
     * @return AbstractFormat
     * @throws FormatsServiceException
     */
    public function getFormat(): AbstractFormat
    {
        $params = $this->getParams()->getDomainParams('format');
        if (empty($params['format'])) {
            throw new FormatsServiceException('Source not set');
        }

        $class = "\\Mithra62\\Export\\Formats\\" . Str::studly($params['format']);
        if (class_exists($class)) {
            $obj = new $class();
            if ($obj instanceof AbstractFormat) {
                $obj->setOptions($params);
                return $obj;
            }
        }

        throw new FormatsServiceException('Format not found ' . $class);
    }
}