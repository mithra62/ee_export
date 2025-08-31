<?php

namespace Mithra62\Export\Plugins;

abstract class AbstractModifier extends AbstractPlugin
{
    /**
     * @var array|string[]
     */
    protected array $params = [];

    /**
     * @var array|string[]
     */
    protected array $_params = [];

    /**
     * @param mixed $value
     * @return mixed
     */
    abstract public function process(mixed $value): mixed;

    /**
     * @return array|string[]
     */
    public function getParams(): array
    {
        return $this->_params;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): AbstractModifier
    {
        $this->_params = $params;
        return $this;
    }

    /**
     * @param string $param
     * @param string $default
     * @return string
     */
    protected function getParam(string $param, string $default = ''): string
    {
        $params = array_flip($this->params);
        $return = $default;
        if(isset($params[$param])){
            $set = $this->getParams();
            if(isset($set[$params[$param]])) {
                $return = $set[$params[$param]];
            }
        }

        return $return;

    }
}