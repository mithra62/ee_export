<?php
namespace Mithra62\Export\Services;

use Mithra62\Export\Traits\ParamsTrait;
use Mithra62\Export\Exceptions\Services\ExportServiceException;

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
        $this->output = $output;
        return $this;
    }

    /**
     * @return OutputService
     * @throws ExportServiceException
     */
    public function getOutput(): OutputService
    {
        if(is_null($this->getParams())) {
            throw new ExportServiceException("Parameters is null");
        }

        return $this->output->setParams($this->getParams());
    }

    /**
     * @param SourcesService|null $sources
     * @return $this
     * @throws ExportServiceException
     */
    public function setSources(SourcesService $sources = null): ExportService
    {
        if(is_null($this->getParams())) {
            throw new ExportServiceException("Parameters is null");
        }
        $this->sources = $sources;
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
     * @return bool
     * @throws ExportServiceException
     */
    public function validate(): bool
    {
        $result = $this->getSources()->getSource()->validate();
        if (!$result->isValid()) {
            $this->errors = $result->getAllErrors();
        }

        $result = $this->getOutput()->getDestination()->validate();
        print_r($this->errors);
        //$errors =
        echo 'fdsa';
        exit;
    }

}