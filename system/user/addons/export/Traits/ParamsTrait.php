<?php

namespace Mithra62\Export\Traits;

use Mithra62\Export\Services\ParamsService;

trait ParamsTrait
{
    /**
     * @var ParamsService
     */
    protected ParamsService $params;

    /**
     * @param ParamsService|null $params
     * @return $this
     */
    public function setParams(ParamsService $params = null): static
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return ParamsService
     */
    public function getParams(): ParamsService
    {
        return $this->params;
    }

    /**
     * @param string $key
     * @param $default
     * @return mixed
     */
    public function getParam(string $key, $default = null)
    {
        return $this->params->get($key, $default);
    }

    /**
     * @param string $key
     * @param $value
     * @return $this
     */
    public function setParam(string $key, $value = null): static
    {
        $this->params->set($key, $value);
        return $this;
    }

}