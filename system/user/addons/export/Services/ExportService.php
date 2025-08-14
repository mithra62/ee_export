<?php
namespace Mithra62\Export\Services;

class ExportService
{
    protected ParamsService $params;

    /**
     * @var array
     */
    protected array $_params = [];

    public function __construct(ParamsService $params)
    {
        $this->params = $params;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): ExportService
    {
        $this->_params = $params;
        return $this;
    }

    /**
     * @param $key
     * @param $default
     * @return mixed|null
     */
    public function getParam($key, $default = null): mixed
    {
        return array_key_exists($key, $this->_params) ? $this->_params[$key] : $default;
    }

}