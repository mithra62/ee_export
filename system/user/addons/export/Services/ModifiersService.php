<?php
namespace Mithra62\Export\Services;

use Mithra62\Export\Exceptions\Services\SourcesServiceException;
use Mithra62\Export\Plugins\AbstractModifier;
use Mithra62\Export\Traits\ParamsTrait;
use Mithra62\Export\Plugins\AbstractSource;
use ExpressionEngine\Library\String\Str;

class ModifiersService extends AbstractService
{
    use ParamsTrait;

    /**
     * @var array
     */
    protected array $processes = [];

    /**
     * @return AbstractModifier
     * @throws SourcesServiceException
     */
    public function getModifier($processor): ?AbstractModifier
    {
        $return = null;
        $class = "\\Mithra62\\Export\\Modifiers\\" . Str::studly($processor);
        if(class_exists($class)) {
            $obj = new $class();
            if($obj instanceof AbstractModifier) {
                $return = $obj;
            }
        }

        return $return;
    }

    /**
     * @param AbstractSource $source
     * @return AbstractSource
     * @throws SourcesServiceException
     */
    public function process(AbstractSource $source): AbstractSource
    {
        $processes = $this->getProcesses();
        if($processes) {
            $data = $source->getExportData();
            foreach($data as $key => $item) {
                foreach($processes as $field => $process) {
                    if(isset($item[$field])) {
                        $data[$key][$field] = $this->runProcesses($item[$field], $process);
                    }
                }
            }

            $source->setExportData($data);
        }

        return $source;
    }

    /**
     * @return array
     */
    public function getProcesses(): array
    {
        $params = $this->getParams()->getDomainParams('modify', false);
        $return = [];
        if($params) {
            $fields = $processors = [];
            foreach($params As $field => $param) {
                $parts = explode('|', $param);
                $fields[$field] = $parts;
                foreacH($parts AS $part) {
                    $processors[$part] = $part;
                }
            }

            $return = $fields;
        }

        return $return;
    }

    /**
     * @param mixed $data
     * @param array $processes
     * @return mixed
     * @throws SourcesServiceException
     */
    protected function runProcesses(mixed $data, array $processes): mixed
    {
        foreach($processes AS $post) {
            $process = $this->getModifier($post);
            if($process instanceof AbstractModifier) {
                $data = $process->process($data);
            }

        }

        return $data;
    }

}