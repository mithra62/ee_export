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
     * @var ModifiersService
     */
    protected ModifiersService $modifiers;

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
    public function setOutput(?OutputService $output = null): ExportService
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
    public function setSources(?SourcesService $sources = null): ExportService
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
     * @param ModifiersService|null $post
     * @return $this
     * @throws ExportServiceException
     */
    public function setModifiers(?ModifiersService $post = null): ExportService
    {
        if (is_null($this->getParams())) {
            throw new ExportServiceException("Parameters is null");
        }

        $this->modifiers = $post->setParams($this->getParams());
        return $this;
    }

    /**
     * @return ModifiersService
     */
    public function getModifiers(): ModifiersService
    {
        return $this->modifiers->setParams($this->getParams());
    }

    /**
     * @param FormatsService|null $formats
     * @return $this
     * @throws ExportServiceException
     */
    public function setFormats(?FormatsService $formats = null): ExportService
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
        $source = $this->getSources()->getSource();

        if ($source->supportsStreaming()) {
            $this->buildStreaming($source);
            return;
        }

        $source->compile();

        $modifiers = $this->getModifiers();
        $source = $modifiers->process($source);

        $format = $this->getFormats()->getFormat();
        $path = $format->compile($source);

        $this->deliver($path);
    }

    protected function buildStreaming(\Mithra62\Export\Plugins\AbstractSource $source): void
    {
        $format = $this->getFormats()->getFormat();
        $modifiers = $this->getModifiers();

        // $path is set only after finalizeFile() returns successfully.
        // The catch block uses it to decide whether to call finalizeFile()
        // (to close the file handle) before attempting cleanup.
        $path = null;

        try {
            $source->openStream();
            $header_written = false;

            while (true) {
                $chunk = $source->nextChunk();
                if (empty($chunk)) {
                    break;
                }

                $chunk = $modifiers->processChunk($chunk);

                if (!$header_written) {
                    $format->openFile($chunk[0]);
                    $header_written = true;
                }

                $format->writeChunk($chunk);
            }

            $source->closeStream();
            $path = $format->finalizeFile();
            $this->deliver($path);

        } catch (\Throwable $e) {
            // Ensure the temp export file is removed on any failure so that
            // partial files do not accumulate in PATH_CACHE/export/.
            //
            // If finalizeFile() was never called ($path === null) the file handle
            // is still open; call finalizeFile() first to close it before unlinking.
            // If finalizeFile() itself throws (e.g. format was never opened because
            // NoDataException fired before the first chunk), we swallow that inner
            // error and leave $path null — there is nothing to remove.
            if ($path === null) {
                try {
                    $path = $format->finalizeFile();
                } catch (\Throwable) {
                    // Format was never opened; no file to clean up.
                }
            }

            if ($path !== null && file_exists($path)) {
                @unlink($path);
            }

            throw $e;
        }
    }

    protected function deliver(string $path): void
    {
        $output = $this->getOutput()->getDestination();
        if ($output->process($path) !== false) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        if ($output->shouldDie()) {
            exit;
        }
    }
}