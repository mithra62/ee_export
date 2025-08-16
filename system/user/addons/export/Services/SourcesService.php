<?php
namespace Mithra62\Export\Services;

use Mithra62\Export\Exceptions\Services\SourcesServiceException;
use Mithra62\Export\Plugins\AbstractSource;
use Mithra62\Export\Traits\ParamsTrait;

class SourcesService extends AbstractService
{
    use ParamsTrait;

    public function run()
    {

    }

    /**
     * @return AbstractSource
     * @throws SourcesServiceException
     */
    public function getSource(): AbstractSource
    {
        $params = $this->getParams()->getDomainParams('source');
        if(empty($params['source'])) {
            throw new SourcesServiceException('Source not set');
        }

        $class = "\\Mithra62\\Export\\Sources\\" . ucfirst($params['source']);
        if(class_exists($class)) {
            $obj = new $class();
            if($obj instanceof AbstractSource) {
                $obj->setOptions($params);
                return $obj;
            }
        }

        throw new SourcesServiceException('Source not found ' . $class);
    }
}