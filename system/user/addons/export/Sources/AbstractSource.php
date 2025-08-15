<?php
namespace Mithra62\Export\Sources;

use ExpressionEngine\Service\Validation\ValidationAware;
use Mithra62\Export\Traits\ValidateTrait;
use ExpressionEngine\Service\Validation\Result AS ValidationResult;

abstract class AbstractSource implements ValidationAware
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
    public function setOptions(array $options)
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