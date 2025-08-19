<?php

namespace Mithra62\Export\Services;

use Mithra62\Export\Exceptions\Services\ExportServiceException;
use Mithra62\Export\Exceptions\Services\FormatsServiceException;
use Mithra62\Export\Exceptions\Services\OutputServiceException;
use Mithra62\Export\Exceptions\Services\SourcesServiceException;
use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Traits\ParamsTrait;

class ExportService extends AbstractService
{
    use ParamsTrait;

    /**
     * @var OutputService
     */
    protected OutputService $output;

    /**
     * @var SourcesService
     */
    protected SourcesService $sources;

    /**
     * @var FormatsService
     */
    protected FormatsService $formats;

    /**
     * @var array
     */
    protected array $errors = [];

    /**
     * @var string
     */
    protected string $cache_file = '';

    /**
     * @var string
     */
    protected string $formatted_export = '';

    /**
     * @param ParamsService|null $params
     * @param OutputService|null $output
     * @param SourcesService|null $sources
     * @param FormatsService|null $formats
     */
    public function __construct(ParamsService $params = null, OutputService $output = null, SourcesService $sources = null, FormatsService $formats = null)
    {
        if ($params !== null) {
            $this->params = $params;
        }

        if ($output !== null) {
            $this->output = $output;
        }

        if ($sources !== null) {
            $this->sources = $sources;
        }

        if ($formats !== null) {
            $this->formats = $formats;
        }
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array $parameters
     * @return $this
     */
    public function setParameters(array $parameters): ExportService
    {
        $this->getParams()->setParams($parameters);
        return $this;
    }

    /**
     * @param OutputService|null $output
     * @return $this
     */
    public function setOutput(OutputService $output = null): ExportService
    {
        if (is_null($this->getParams())) {
            throw new ExportServiceException("Parameters is null");
        }

        $this->output = $output->setParams($this->getParams());
        return $this;
    }

    /**
     * @return OutputService
     * @throws ExportServiceException
     */
    public function getOutput(): OutputService
    {
        return $this->output;
    }

    /**
     * @param SourcesService|null $sources
     * @return $this
     * @throws ExportServiceException
     */
    public function setSources(SourcesService $sources = null): ExportService
    {
        if (is_null($this->getParams())) {
            throw new ExportServiceException("Parameters is null");
        }

        $this->sources = $sources->setParams($this->getParams());
        return $this;
    }

    /**
     * @return SourcesService
     */
    public function getSources(): SourcesService
    {
        return $this->sources->setParams($this->getParams());
    }

    /**
     * @param FormatsService|null $formats
     * @return $this
     * @throws ExportServiceException
     */
    public function setFormats(FormatsService $formats = null): ExportService
    {
        if (is_null($this->getParams())) {
            throw new ExportServiceException("Parameters is null");
        }

        $this->formats = $formats->setParams($this->getParams());
        return $this;
    }

    /**
     * @return FormatsService
     */
    public function getFormats(): FormatsService
    {
        return $this->formats->setParams($this->getParams());
    }

    /**
     * @return bool
     * @throws ExportServiceException
     * @throws FormatsServiceException
     * @throws OutputServiceException
     * @throws SourcesServiceException
     */
    public function validate(): bool
    {
        $result = $this->getSources()->getSource()->validate();
        if (!$result->isValid()) {
            $this->errors = $result->getAllErrors();
        }

        $result = $this->getOutput()->getDestination()->validate();
        if (!$result->isValid()) {
            $this->errors = $this->errors + $result->getAllErrors();
        }

        $result = $this->getFormats()->getFormat()->validate();
        if (!$result->isValid()) {
            $this->errors = $this->errors + $result->getAllErrors();
        }

        return count($this->errors) == 0;
    }

    /**
     * @return $this
     * @throws FormatsServiceException
     * @throws NoDataException
     * @throws SourcesServiceException
     */
    public function build(): ExportService
    {
        $source = $this->getSources()->getSource();
        $this->cache_file = realpath($source->compile());

        $format = $this->getFormats()->getFormat();
        $this->formatted_export = $format->setCachePath($this->cache_file)->compile();
        return $this;
    }

    /**
     * @return void
     * @throws ExportServiceException
     * @throws OutputServiceException
     */
    public function out(): void
    {
        if(file_exists($this->cache_file)) {
            unlink($this->cache_file);
        }

        if(!$this->formatted_export) {
            throw new ExportServiceException("No cache file is set");
        }

        $output = $this->getOutput()->getDestination();
        if($output->process($this->formatted_export) !== false) {
            if(file_exists($this->formatted_export)) {
                unlink($this->formatted_export);
            }
        }

        exit;
    }
}