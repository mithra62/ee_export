<?php

namespace Mithra62\Export\Models;

use ExpressionEngine\Service\Model\Model;

/**
 * ExportConfiguration — persisted, named export configuration.
 *
 * Each record stores the source type and a JSON settings blob that contains
 * all source:*, format:*, output:*, modify:*, fields, and exclude keys in
 * exactly the same shape that ExportService::setParameters() expects (after
 * CpService::buildParamsFromSettings() adds the top-level 'source' key back).
 *
 * Table: exp_export_configurations
 */
class ExportConfiguration extends Model
{
    protected static $_primary_key = 'id';
    protected static $_table_name  = 'export_configurations';

    protected static $_typed_columns = [
        'site_id'    => 'int',
        'created_at' => 'int',
        'updated_at' => 'int',
    ];

    /** @var int */
    protected $id;

    /** @var int */
    protected $site_id;

    /** @var string Human-readable label shown in the CP index table. */
    protected $name;

    /** @var string Source key: entries|members|grid|fluid|sql */
    protected $source;

    /** @var string JSON blob — see getSettings() / setSettings(). */
    protected $settings;

    /** @var int Unix timestamp */
    protected $created_at;

    /** @var int Unix timestamp */
    protected $updated_at;

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Decode the settings JSON blob into an associative array.
     * Returns an empty array when settings is null or empty.
     */
    public function getSettings(): array
    {
        return json_decode($this->settings ?: '{}', true) ?? [];
    }

    /**
     * Encode and store an associative settings array.
     */
    public function setSettings(array $settings): void
    {
        // setRawProperty() triggers backupIfChanging() so the ORM marks this
        // property dirty and includes it in UPDATE queries. Direct $this->settings
        // assignment bypasses __set() / setProperty() and the model never marks
        // the column as modified, causing edits to silently not save.
        $this->setRawProperty('settings', json_encode($settings));
    }

    /**
     * Convenience: return a formatted creation timestamp.
     */
    public function getFormattedCreatedAt(string $format = 'Y-m-d H:i'): string
    {
        return $this->created_at ? date($format, $this->created_at) : '';
    }
}
