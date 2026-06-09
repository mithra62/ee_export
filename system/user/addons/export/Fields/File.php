<?php

namespace Mithra62\Export\Fields;

use Mithra62\Export\Plugins\AbstractField;

/**
 * Handles EE `file` field types.
 * Resolves the stored `{filedir_N}filename.ext` token to an absolute URL.
 */
class File extends AbstractField
{
    public function process(mixed $raw_value, array $field_info, int $entry_id, array $context = []): mixed
    {
        return ee('export:EntryService')->getImageUrl($raw_value);
    }
}
