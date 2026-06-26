<?php

namespace Mithra62\Export\Plugins;

use ExpressionEngine\Service\Logger\File;
use ExpressionEngine\Service\Validation\Result as ValidationResult;
use ExpressionEngine\Service\Validation\ValidationAware;
use Mithra62\Export\Traits\LoggerTrait;
use Mithra62\Export\Traits\ValidateTrait;

abstract class AbstractPlugin implements ValidationAware
{
    use ValidateTrait,
        LoggerTrait;

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * @var string
     */
    protected string $cache_path = '';

    /**
     * @var string
     */
    protected string $cache_filename = '';

    /**
     * @var File|null
     */
    protected ?File $cache_file = null;

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
        return $this->options;
    }

    /**
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        return $this->check($this->getOptions());
    }

    /**
     * Describe this plugin's Control Panel form fields declaratively.
     *
     * Returns an empty array by default — plugins with no CP presence (or that
     * only support template-tag usage) need not override this method.
     *
     * Each descriptor: ['name', 'type', 'label', 'desc'?, 'required'?, 'default'?,
     * 'choices'?, 'choices_callback'?, 'value_callback'?, 'content'?,
     * 'content_callback'?, 'maxlength'?, 'placeholder'?, 'group'?, 'scoped'?].
     * See EXTENDING.md "CP Form Fields" for the full contract.
     *
     * @param array $context Always contains: 'settings' (bare-name-stripped settings
     *                       for this plugin instance), 'cp' (CpService), 'source_key'
     *                       (the registered key this instance was resolved from), and
     *                       'field_name' (populated by the renderer per-field, for
     *                       callbacks that need to self-reference their rendered name).
     * @return array<int, array>
     */
    public function getCpFields(array $context = []): array
    {
        return [];
    }

    /**
     * Optional CP choice-list label override for this plugin's registered key.
     * Null falls back to a humanized version of the key.
     *
     * @return string|null
     */
    public function getCpLabel(): ?string
    {
        return null;
    }

    /**
     * @return string
     */
    public function generateCachePath(): string
    {
        if (!$this->cache_path) {
            $cache_path = PATH_CACHE . 'export/';
            if (!is_dir($cache_path)) {
                mkdir($cache_path, 0755, true);
                @file_put_contents($cache_path . 'index.html', '');
            }

            $this->cache_path = $cache_path;
        }

        return $this->cache_path;
    }

    /**
     * @return File
     * @throws \Exception
     */
    protected function getCacheFile(): File
    {
        if (is_null($this->cache_file)) {
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
     * @return string
     */
    public function getCacheFilename(): string
    {
        if (!$this->cache_filename) {
            $this->cache_filename = ee()->functions->random('alpha', 13) . '.tmp';
        }

        return $this->cache_filename;
    }

    /**
     * @return string
     */
    public function getCacheDirPath(): string
    {
        return $this->generateCachePath();
    }
}