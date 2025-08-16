<?php
namespace Mithra62\Export\Plugins;

use ExpressionEngine\Service\Logger\File;
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
     * @var string
     */
    protected string $cache_path = '';

    /**
     * @var File|null
     */
    protected ? File $cache_file = null;

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
     * @param string $key
     * @param $default
     * @return mixed|null
     */
    public function getOption(string $key, $default = null)
    {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
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

    /**
     * @return string
     */
    public function generateCachePath(): string
    {
        if(!$this->cache_path) {
            $cache_path = PATH_CACHE . 'export/';
            if(!is_dir($cache_path)) {
                mkdir($cache_path, 0777, true);
            }

            $this->cache_path = $cache_path . ee()->functions->random('alpha', 13) . '.tmp';
        }

        return $this->cache_path;
    }

    /**
     * @return File
     * @throws \Exception
     */
    protected function getCacheFile(): File
    {
        if(is_null($this->cache_file)) {
            $this->cache_file = new File($this->generateCachePath(), ee('Filesystem'));
        }
        return $this->cache_file;
    }

    /**
     * @return File
     * @throws \Exception
     */
    protected function truncateCache(): File
    {
        $this->getCacheFile()->truncate();
        return $this->getCacheFile();
    }

    /**
     * @param array $data
     * @return void
     * @throws \Exception
     */
    protected function writeCache(array $data)
    {
        $this->truncateCache()->log(json_encode($data));
    }

    /**
     * @param string $content
     * @param string $path
     * @return void
     * @throws \Exception
     */
    protected function writeContent(string $content, string $path): AbstractPlugin
    {
        $file = new File($path, ee('Filesystem'));
        $file->log($content);
        return $this;
    }

    /**
     * @return string
     */
    public function getCachePath(): string
    {
        return $this->cache_path;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setCachePath(string $path): AbstractPlugin
    {
        $this->cache_path = $path;
        return $this;
    }

    /**
     * @return array
     */
    public function getCacheContent(): array
    {
        $return = [];
        $content = file_get_contents($this->getCachePath());
        if($content) {
            $return = json_decode($content, true);
        }

        return $return;
    }

    /**
     * @return string
     */
    public function getCacheFilename(): string
    {
        $info = pathinfo($this->getCachePath());
        return $info['filename'];
    }

    /**
     * @return string
     */
    public function getCacheDirPath(): string
    {
        $info = pathinfo($this->getCachePath());
        return $info['dirname'];
    }
}