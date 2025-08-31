<?php

namespace Mithra62\Export\Services;

use ExpressionEngine\Library\String\Str;
use Mithra62\Export\Exceptions\Services\SourcesServiceException;
use Mithra62\Export\Plugins\AbstractModifier;
use Mithra62\Export\Plugins\AbstractSource;
use Mithra62\Export\Traits\ParamsTrait;

class ModifiersService extends AbstractService
{
    use ParamsTrait;

    /**
     * @var array
     */
    protected array $processes = [];

    /**
     * @param $processor
     * @param array $params
     * @return AbstractModifier|null
     */
    protected function getModifier($processor, array $params = []): ?AbstractModifier
    {
        $return = null;
        $class = "\\Mithra62\\Export\\Modifiers\\" . Str::studly($processor);
        if (class_exists($class)) {
            $obj = new $class();
            if ($obj instanceof AbstractModifier) {
                if($params) {
                    $obj->setParams($params);
                }
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
        $processes = $this->getModifiers();
        if ($processes) {
            $data = $source->getExportData();
            foreach ($data as $key => $item) {
                foreach ($processes as $field => $process) {
                    if (isset($item[$field])) {
                        $data[$key][$field] = $this->runModifiers($item[$field], $process);
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
    protected function getModifiers(): array
    {
        $params = $this->getParams()->getDomainParams('modify', false);
        $return = [];
        if ($params) {
            $fields = $processors = [];
            foreach ($params as $field => $param) {
                $parts = explode('|', $param);
                $fields[$field] = $parts;
                foreach ($parts as $part) {
                    $processors[$part] = $part;
                }
            }

            $return = $fields;
        }

        return $return;
    }

    /**
     * @param mixed $data
     * @param array $modifiers
     * @return mixed
     */
    protected function runModifiers(mixed $data, array $modifiers): mixed
    {
        foreach ($modifiers as $modifier) {
            $params = [];

            preg_match_all('/\\[(.*?)\\]/', $modifier, $params);

            $pattern = '/\[.*?\]/';
            $name = preg_replace($pattern, '', $modifier);

            $process = $this->getModifier($name, $params[1]);
            if ($process instanceof AbstractModifier) {
                $data = $process->process($data);
            }

        }

        return $data;
    }

}