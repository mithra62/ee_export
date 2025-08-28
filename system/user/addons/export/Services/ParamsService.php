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

    /**
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): ParamsService
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param string $domain
     * @return array
     */
    public function getDomainParams(string $domain, bool $include_all = true): array
    {
        $return = [];
        $search = trim($domain) . ':';
        foreach($this->params AS $key => $value) {
            if(str_starts_with($key, $search)) {
                $key = str_replace($search, '', $key);
                $return[$key] = $value;
            } elseif($include_all) {
                $return[$key] = $value;
            }
        }

        return $return;
    }

    /**
     * @return array
     */
    public function getAllParams(): array
    {
        return $this->params;
    }
}