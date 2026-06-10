<?php

namespace Mithra62\Export\Services;

use Mithra62\Export\Traits\LoggerTrait;

abstract class AbstractService
{
    use LoggerTrait;

    /**
     * @var int|null
     */
    protected ?int $site_id = 1;

    /**
     * Cross-instance cache for provider maps keyed by layer name.
     * Static so the provider scan runs at most once per PHP request
     * regardless of how many service instances are created.
     *
     * @var array<string, array<string, class-string>>
     */
    protected static array $provider_map_cache = [];

    /**
     * @param int $site_id
     * @return $this
     */
    public function setSiteId(int $site_id): AbstractService
    {
        $this->logger()->debug('Set site_id');
        $this->site_id = $site_id;
        return $this;
    }

    /**
     * @return int
     */
    public function getSiteId(): ?int
    {
        return $this->site_id;
    }

    /**
     * Return every class registered for a given Export extension layer across
     * all installed EE addons.
     *
     * Any addon (including Export itself) declares handlers in addon.setup.php:
     *
     *   'export' => [
     *       'sources'   => ['my_source'   => MyAddon\Export\Sources\MySource::class],
     *       'formats'   => ['my_format'   => MyAddon\Export\Formats\MyFormat::class],
     *       'outputs'   => ['my_output'   => MyAddon\Export\Output\MyOutput::class],
     *       'modifiers' => ['my_modifier' => MyAddon\Export\Modifiers\MyModifier::class],
     *       'fields'    => ['my_field'    => MyAddon\Export\Fields\MyField::class],
     *   ]
     *
     * Export's own declarations form the baseline for each layer. Third-party
     * declarations are merged after and can override built-ins when needed.
     *
     * Results are cached statically so the provider scan runs at most once per
     * PHP request per layer.
     *
     * @param  string $layer  One of: sources, formats, outputs, modifiers, fields
     * @return array<string, class-string>
     */
    public function getProviderMap(string $layer): array
    {
        if (isset(static::$provider_map_cache[$layer])) {
            return static::$provider_map_cache[$layer];
        }

        $internal = [];
        $external = [];

        foreach (ee('App')->getProviders() as $provider_key => $provider) {
            $config = $provider->get('export');
            if (!empty($config[$layer]) && is_array($config[$layer])) {
                if ($provider_key === 'export') {
                    $internal = array_merge($internal, $config[$layer]);
                } else {
                    $external = array_merge($external, $config[$layer]);
                }
            }
        }

        // Built-in handlers form the baseline; third-party declarations override them.
        return static::$provider_map_cache[$layer] = array_merge($internal, $external);
    }
}