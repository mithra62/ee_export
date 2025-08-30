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
     * @var PostProcessService
     */
    protected PostProcessService $post;

    /**
     * @var array
     */
    protected array $errors = [];

    /**
     * @var string
     */
    protected string $cache_file = '';

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
     * @param PostProcessService|null $post
     * @return $this
     * @throws ExportServiceException
     */
    public function setPostProcess(PostProcessService $post = null): ExportService
    {
        if (is_null($this->getParams())) {
            throw new ExportServiceException("Parameters is null");
        }

        $this->post = $post->setParams($this->getParams());
        return $this;
    }

    /**
     * @return PostProcessService
     */
    public function getPostProcess(): PostProcessService
    {
        return $this->post->setParams($this->getParams());
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
     * @return void
     * @throws ExportServiceException
     * @throws FormatsServiceException
     * @throws NoDataException
     * @throws OutputServiceException
     * @throws SourcesServiceException
     */
    public function build(): void
    {
        $source = $this->getSources()->getSource()->compile();

        $post = $this->getPostProcess();
        $source = $post->process($source);

        $format = $this->getFormats()->getFormat();
        $path = $format->compile($source);

        $output = $this->getOutput()->getDestination();
        if ($output->process($path) !== false) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}