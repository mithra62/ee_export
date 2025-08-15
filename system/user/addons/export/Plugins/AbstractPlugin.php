<?php
namespace Mithra62\Export\Plugins;

use ExpressionEngine\Service\Validation\Result as ValidationResult;
use ExpressionEngine\Service\Validation\ValidationAware;
use Mithra62\Export\Traits\ValidateTrait;

abstract class AbstractPlugin implements ValidationAware
{
    use ValidateTrait;

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): AbstractPlugin
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->options as $key => $value) {
            $data[$key] = $this->{$key};
        }

        return $data;
    }

    /**
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        return $this->check($this->getOptions());
    }
}