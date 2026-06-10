<?php

namespace Mithra62\Export\Tags;

/**
 * Runs a saved Control Panel export configuration by ID.
 *
 * Template syntax:
 *   {exp:export:preset id="42"}
 *
 * Inline tag params override stored settings with one exception: allowed_roles
 * is a CP-defined security boundary and cannot be overridden from a template.
 */
class Preset extends AbstractTag
{
    public function process(): void
    {
        $config_id = (int) $this->param('id', 0);
        if (! $config_id) {
            show_error('export:preset requires a numeric id param.');
            return;
        }

        $config = ee('Model')
            ->get('export:ExportConfiguration')
            ->filter('id', $config_id)
            ->filter('site_id', ee()->config->item('site_id'))
            ->first();

        if (! $config) {
            ee()->TMPL->no_results();
            return;
        }

        $params = ee('export:CpService')->buildParamsFromSettings(
            $config->source,
            $config->getSettings()
        );

        // allowed_roles is a security boundary set in the CP; templates cannot
        // override it. All other params can be overridden inline.
        foreach ($this->params() as $key => $value) {
            if ($key !== 'id' && $key !== 'allowed_roles' && $value !== '' && $value !== false) {
                $params[$key] = $value;
            }
        }

        $this->compile($params);
    }
}
