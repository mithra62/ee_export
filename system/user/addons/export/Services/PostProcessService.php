<?php
namespace Mithra62\Export\Services;

use Mithra62\Export\Exceptions\Services\SourcesServiceException;
use Mithra62\Export\Plugins\AbstractPost;
use Mithra62\Export\Traits\ParamsTrait;
use Mithra62\Export\Plugins\AbstractSource;

class PostProcessService extends AbstractService
{
    use ParamsTrait;

    /**
     * @return AbstractPost
     * @throws SourcesServiceException
     */
    public function getPost(): AbstractPost
    {
        $params = $this->getParams()->getDomainParams('post');
        if(empty($params['post'])) {
            throw new PostProcessServiceException('Source not set');
        }

        $class = "\\Mithra62\\Export\\Post\\" . ucfirst($params['source']);
        if(class_exists($class)) {
            $obj = new $class();
            if($obj instanceof AbstractPost) {
                $obj->setOptions($params);
                return $obj;
            }
        }

        throw new SourcesServiceException('Source not found ' . $class);
    }

    /**
     * @param AbstractSource $source
     * @return AbstractSource
     */
    public function process(AbstractSource $source): AbstractSource
    {
        return $source;
        //
        $params = $this->getParams()->getDomainParams('post', false);
        if($params) {
            $fields = $processors = [];
            foreach($params As $field => $param) {
                $parts = explode('|', $param);
                $fields[$field] = $parts;
                foreacH($parts AS $part) {
                    $processors[$part] = $part;
                }
            }
            print_r($processors);
            print_r($fields);
            exit;
        }


        return $data;
    }
}