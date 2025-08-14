<?php
namespace Mithra62\Export\Services;

class ParamsService
{
    /**
     * @var array
     */
    protected array $params = [];

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function set(string $key, mixed $value): ParamsService
    {
        $this->params[$key] = $value;
        return $this;
    }

    public function setParams(array $params): ParamsService
    {
        $this->params = $params;
        return $this;
    }
}