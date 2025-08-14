<?php
namespace Mithra62\Export\Services;

class ExportService
{
    /**
     * @var ParamsService
     */
    protected ParamsService $params;

    /**
     * @var OutputService
     */
    protected OutputService $output;

    /**
     * @var SourcesService
     */
    protected SourcesService $sources;

    /**
     * @var array
     */
    protected array $errors = [];

    public function __construct(ParamsService $params = null, OutputService $output = null, SourcesService $sources = null)
    {
        if($params !== null) {
            $this->params = $params;
        }

        if($output !== null) {
            $this->output = $output;
        }

        if($sources !== null) {
            $this->sources = $sources;
        }
    }

    /**
     * @param ParamsService|null $params
     * @return $this
     */
    public function setParams(ParamsService $params = null): ExportService
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
     * @param OutputService|null $output
     * @return $this
     */
    public function setOutput(OutputService $output = null): ExportService
    {
        $this->output = $output;
        return $this;
    }

    /**
     * @return OutputService
     */
    public function getOutput(): OutputService
    {
        return $this->output;
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
     * @param SourcesService|null $sources
     * @return $this
     */
    public function setSources(SourcesService $sources = null): ExportService
    {
        $this->sources = $sources;
        return $this;
    }

    /**
     * @return SourcesService
     */
    public function getSources(): SourcesService
    {
        return $this->sources;
    }

    public function validate()
    {
        //$errors =
        echo 'fdsa';
        exit;
    }

}