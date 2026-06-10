<?php

namespace Mithra62\Export\Services;

use ExpressionEngine\Library\String\Str;
use Mithra62\Export\Exceptions\Services\SourcesServiceException;
use Mithra62\Export\Plugins\AbstractSource;
use Mithra62\Export\Traits\ParamsTrait;

class SourcesService extends AbstractService
{
    use ParamsTrait;

    /**
     * @return AbstractSource
     * @throws SourcesServiceException
     */
    public function getSource(): AbstractSource
    {
        $params = $this->getParams()->getDomainParams('source');
        if (empty($params['source'])) {
            throw new SourcesServiceException('Source not set');
        }

        $name = $params['source'];

        // Provider map takes precedence; namespace resolution is the fallback.
        $map = $this->getProviderMap('sources');
        $class = $map[$name] ?? ("\\Mithra62\\Export\\Sources\\" . Str::studly($name));

        if (class_exists($class)) {
            $obj = new $class();
            if ($obj instanceof AbstractSource) {
                $obj->setOptions($params);
                return $obj;
            }
        }

        throw new SourcesServiceException('Source not found: ' . $name);
    }
}