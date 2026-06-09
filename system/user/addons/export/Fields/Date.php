<?php

namespace Mithra62\Export\Fields;

use Mithra62\Export\Plugins\AbstractField;

/**
 * Handles EE `date` field types.
 * Returns the raw Unix timestamp as an integer so the `ee_date` modifier
 * (or any other modifier) can format it downstream.
 */
class Date extends AbstractField
{
    public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
    {
        return (int) ($raw_value ?? 0);
    }
}
