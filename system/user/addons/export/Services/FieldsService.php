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
     * Return every registered field handler across all EE providers.
     *
     * Delegates to AbstractService::getProviderMap('fields'), which is shared
     * with SourcesService, FormatsService, OutputService, and ModifiersService
     * to ensure the provider scan runs at most once per PHP request per layer.
     *
     * @return array<string, class-string<AbstractField>>
     */
    public function getAll(): array
    {
        return $this->getProviderMap('fields');
    }

    /**
     * Return an instantiated handler for the given EE field type, or null if none is registered.
     */
    public function getField(string $field_type): ?AbstractField
    {
        if (array_key_exists($field_type, $this->cache)) {
            return $this->cache[$field_type];
        }

        $map   = $this->getAll();
        $class = $map[$field_type] ?? null;

        if (!$class || !class_exists($class)) {
            return $this->cache[$field_type] = null;
        }

        $obj = new $class();

        return $this->cache[$field_type] = ($obj instanceof AbstractField) ? $obj : null;
    }
}
