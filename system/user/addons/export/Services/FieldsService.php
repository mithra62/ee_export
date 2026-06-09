<?php

namespace Mithra62\Export\Services;

use Mithra62\Export\Plugins\AbstractField;

class FieldsService extends AbstractService
{
    /**
     * Resolved instance cache keyed by field_type.
     * A null entry means the type was looked up and had no registered handler.
     *
     * @var array<string, AbstractField|null>
     */
    protected array $cache = [];

    /**
     * Cached result of getAll() so provider scanning happens only once per request.
     * @var array<string, class-string<AbstractField>>|null
     */
    protected ?array $all_cache = null;

    /**
     * Return every registered field handler across all EE providers.
     *
     * Any add-on (including Export itself) can declare handlers in addon.setup.php:
     *
     *   'export' => [
     *       'fields' => [
     *           'my_field_type' => \MyAddon\Export\Fields\MyFieldType::class,
     *       ],
     *   ]
     *
     * Third-party declarations are merged after internal ones, allowing overrides.
     *
     * @return array<string, class-string<AbstractField>>
     */
    public function getAll(): array
    {
        if ($this->all_cache !== null) {
            return $this->all_cache;
        }

        $map = [];
        $internal = [];

        foreach (ee('App')->getProviders() as $providerKey => $provider) {
            $config = $provider->get('export');
            if (!empty($config['fields']) && is_array($config['fields'])) {
                if ($providerKey === 'export') {
                    $internal = array_merge($internal, $config['fields']);
                } else {
                    $map = array_merge($map, $config['fields']);
                }
            }
        }

        // Built-in handlers form the baseline; third-party declarations override them.
        return $this->all_cache = array_merge($internal, $map);
    }

    /**
     * Return an instantiated handler for the given EE field type, or null if none is registered.
     */
    public function getField(string $field_type): ?AbstractField
    {
        if (array_key_exists($field_type, $this->cache)) {
            return $this->cache[$field_type];
        }

        $map = $this->getAll();
        $class = $map[$field_type] ?? null;

        if (!$class || !class_exists($class)) {
            return $this->cache[$field_type] = null;
        }

        $obj = new $class();

        return $this->cache[$field_type] = ($obj instanceof AbstractField) ? $obj : null;
    }
}
