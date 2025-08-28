<?php

namespace Mithra62\Export\Plugins;

use Mithra62\Export\Exceptions\Sources\NoDataException;
use Mithra62\Export\Services\PostProcessService;

abstract class AbstractSource extends AbstractPlugin
{
    /**
     * @var array
     */
    protected array $export_data = [];

    /**
     * @var PostProcessService
     */
    protected PostProcessService $post_process;

    /**
     * @return string
     * @throws NoDataException
     * @throws \Exception
     */
    abstract public function compile(): string;

    /**
     * @param PostProcessService $post_process
     * @return $this
     */
    public function setPostProcess(PostProcessService $post_process): AbstractSource
    {
        $this->post_process = $post_process;
        return $this;
    }

    /**
     * @return PostProcessService
     */
    public function getPostProcess(): PostProcessService
    {
        return $this->post_process;
    }

    /**
     * @return array
     */
    public function getExportData(): array
    {
        return $this->export_data;
    }

    /**
     * @param array $export_data
     * @return $this
     */
    public function setExportData(array $export_data): AbstractSource
    {
        $this->export_data = $export_data;
        return $this;
    }

    /**
     * @param array $data
     * @return array
     */
    public function cleanFields(array $data): array
    {
        if ($this->getOption('fields')) {
            foreach ($data as $key => $value) {
                if (!in_array($key, $this->getOption('fields'))) {
                    unset($data[$key]);
                }
            }

            //now we order 'em
            $return = [];
            foreach ($this->getOption('fields') as $field) {
                if(isset($data[$field])) {
                    $return[$field] = $data[$field];
                }
            }

            $data = $return;
        }

        return $this->postProcess($data);
    }

    /**
     * @param array $data
     * @return array
     */
    public function postProcess(array $data): array
    {
        $params = $this->getPostProcess()->getParams()->getDomainParams('post', false);
        if($params) {

            print_r($params);
            exit;
        }


        return $data;
    }
}