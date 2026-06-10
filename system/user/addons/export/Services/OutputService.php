<?php

namespace Mithra62\Export\Services;

use ExpressionEngine\Library\String\Str;
use Mithra62\Export\Exceptions\Services\OutputServiceException;
use Mithra62\Export\Plugins\AbstractDestination;
use Mithra62\Export\Traits\ParamsTrait;

class OutputService extends AbstractService
{
    use ParamsTrait;

    /**
     * @return AbstractDestination
     * @throws OutputServiceException
     */
    public function getDestination(): AbstractDestination
    {
        $params = $this->getParams()->getDomainParams('output');
        if (empty($params['output'])) {
            $this->logger()->debug('Output object not set');
            throw new OutputServiceException('Output object not set');
        }

        $name = $params['output'];

        // Provider map takes precedence; namespace resolution is the fallback.
        $map = $this->getProviderMap('outputs');
        $class = $map[$name] ?? ("\\Mithra62\\Export\\Output\\" . Str::studly($name));

        if (class_exists($class)) {
            $obj = new $class();
            if ($obj instanceof AbstractDestination) {
                $obj->setOptions($params);
                return $obj;
            }
        }

        $this->logger()->debug('Output destination not found: ' . $name);
        throw new OutputServiceException('Output destination not found: ' . $name);
    }
}