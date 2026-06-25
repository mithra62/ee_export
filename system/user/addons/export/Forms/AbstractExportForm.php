<?php

namespace Mithra62\Export\Forms;

use ExpressionEngine\Library\CP\Form;
use ExpressionEngine\Library\CP\Form\AbstractForm;
use Mithra62\Export\Plugins\AbstractPlugin;

/**
 * Base class for all Export CP form objects.
 *
 * Extends EE's AbstractForm (which declares generate(): array as the contract)
 * and adds a single shared factory helper so every concrete form class gets a
 * fresh Form instance through the same call rather than each importing `new Form`
 * independently.
 *
 * Also provides the generic, provider-map-driven field renderer shared by any
 * concrete form that needs to expose Source/Format/Output plugin CP fields —
 * built-in and third-party plugins render through this exact same code path via
 * AbstractPlugin::getCpFields()/getCpLabel(). See EXTENDING.md "CP Form Fields".
 *
 * Shared metadata keys (cp_page_title, base_url, save_btn_text,
 * save_btn_text_working) are NOT set here — each route knows its own URL and
 * button labels and stitches them into the array returned by generate() before
 * passing the vars to setView().
 */
abstract class AbstractExportForm extends AbstractForm
{
    /**
     * Return a new, empty CP Form object.
     *
     * Called by every concrete generate() implementation in place of `new Form`
     * so the Form import is declared once in this base class.
     */
    protected function makeForm(): Form
    {
        return new Form;
    }

    /**
     * Build [key => label] choices for a select field from a provider map.
     *
     * @param array<string, class-string> $map         [key => class]
     * @param string                      $lang_prefix Optional lang() key prefix
     *                                                  tried before falling back
     *                                                  to a humanized key.
     * @return array<string, string>
     */
    protected function buildChoicesFromProviderMap(array $map, string $lang_prefix = ''): array
    {
        $choices = [];
        foreach ($map as $key => $class) {
            $choices[$key] = $this->resolveChoiceLabel($class, $key, $lang_prefix);
        }
        return $choices;
    }

    /**
     * Resolve a single provider-map entry's display label.
     *
     * Precedence: the plugin's own getCpLabel() override, then lang('{prefix}{key}')
     * when that resolves to something other than the bare key (EE's lang() echoes
     * the key verbatim when no translation exists), then a humanized fallback of
     * the key so an unlocalized third-party plugin still renders something readable.
     */
    protected function resolveChoiceLabel(string $class, string $key, string $lang_prefix): string
    {
        if (class_exists($class)) {
            $instance = new $class();
            if ($instance instanceof AbstractPlugin) {
                $label = $instance->getCpLabel();
                if ($label !== null) {
                    return $label;
                }
            }
        }

        if ($lang_prefix !== '') {
            $translated = lang($lang_prefix . $key);
            if ($translated !== $lang_prefix . $key) {
                return $translated;
            }
        }

        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * Render every registered key's CP fields for a given layer into a Group.
     *
     * Single rendering path for built-in and third-party plugins. Each
     * registered class instantiates itself and declares its own fields via
     * getCpFields(); this method handles settings extraction, the 'scoped'
     * storage-key flag, and the EE CP/Form Set/Field API calls generically —
     * no per-source/format/output special-casing.
     *
     * @param Form\Group $group     Target form Group (e.g. $src, $fmt, $out)
     * @param array      $map       [key => class] provider map for this layer
     * @param string     $domain    'source'|'format'|'output' — settings prefix domain
     * @param string     $ns_prefix 'src_'|'fmt_'|'output_' — POST field name prefix
     * @param array      $settings  Full decoded settings array (not yet stripped)
     */
    protected function renderPluginCpFields(
        Form\Group $group,
        array $map,
        string $domain,
        string $ns_prefix,
        array $settings
    ): void {
        $domain_prefix = $domain . ':';

        foreach ($map as $key => $class) {
            if (!class_exists($class)) {
                continue;
            }
            $plugin = new $class();
            if (!($plugin instanceof AbstractPlugin)) {
                continue;
            }

            $group_name = $domain . '_' . $key;
            $field_name_prefix = $ns_prefix . $key . '_';
            $scoped_prefix = $key . ':';

            // Bare-name settings for this domain (e.g. 'source:channel' -> 'channel')
            $bare_settings = [];
            foreach ($settings as $k => $v) {
                if (str_starts_with($k, $domain_prefix)) {
                    $bare_settings[substr($k, strlen($domain_prefix))] = $v;
                }
            }

            $context = [
                'settings' => $bare_settings,
                'cp' => ee('export:CpService'),
                'source_key' => $key,
            ];

            foreach ($plugin->getCpFields($context) as $descriptor) {
                $field_context = $context;

                // Scoped fields read/write '{key}:{name}' instead of '{domain}:{name}'
                // so switching source/format/output in the editor never cross-populates
                // the wrong value (generalizes the Grid/Fluid channel/field fix).
                if (!empty($descriptor['scoped'])) {
                    $scoped_key = $scoped_prefix . $descriptor['name'];
                    if (array_key_exists($scoped_key, $settings)) {
                        $field_context['settings'][$descriptor['name']] = $settings[$scoped_key];
                    }
                }

                $this->renderFieldDescriptor($group, $descriptor, $group_name, $field_name_prefix, $field_context);
            }
        }
    }

    /**
     * Render a single field descriptor into a FieldSet using the EE CP/Form API.
     *
     * @param Form\Group $group             Target Group
     * @param array      $descriptor        See AbstractPlugin::getCpFields() for the shape
     * @param string     $group_name        Default 'data-group' value for show/hide toggling
     * @param string     $field_name_prefix Prefix applied to the descriptor's bare 'name'
     * @param array      $context           Per-field context; 'field_name' is set here
     */
    protected function renderFieldDescriptor(
        Form\Group $group,
        array $descriptor,
        string $group_name,
        string $field_name_prefix,
        array $context
    ): void {
        // getFieldSet()'s key must be unique within the Group — multiple plugins
        // sharing one Group (e.g. all sources render into the same 'source params'
        // Group) would otherwise collide on a shared label like 'export_field_channel'.
        // The rendered field name is already unique per plugin, so it doubles as the
        // FieldSet key; setTitle() applies the descriptor's lang key for display.
        $field_name = $field_name_prefix . $descriptor['name'];

        $set = $group->getFieldSet($field_name)
            ->set('group', $descriptor['group'] ?? $group_name)
            ->setTitle($descriptor['label']);

        if (!empty($descriptor['desc'])) {
            $set->setDesc($descriptor['desc']);
        }

        $context['field_name'] = $field_name;
        $field = $set->getField($field_name, $descriptor['type']);

        if ($descriptor['type'] === 'html') {
            $content_cb = $descriptor['content_callback'] ?? null;
            $field->setContent($content_cb ? $content_cb($context) : ($descriptor['content'] ?? ''));
            return;
        }

        if (in_array($descriptor['type'], ['select', 'checkbox', 'radio'], true)) {
            $choices_cb = $descriptor['choices_callback'] ?? null;
            $field->setChoices($choices_cb ? $choices_cb($context) : ($descriptor['choices'] ?? []));
        }

        if (!empty($descriptor['maxlength'])) {
            $field->setMaxlength((int) $descriptor['maxlength']);
        }
        if (!empty($descriptor['placeholder'])) {
            $field->setPlaceholder($descriptor['placeholder']);
        }
        if (!empty($descriptor['required'])) {
            $field->setRequired(true);
        }

        $value_cb = $descriptor['value_callback'] ?? null;
        $value = $value_cb
            ? $value_cb($context)
            : ($context['settings'][$descriptor['name']] ?? ($descriptor['default'] ?? ''));
        $field->setValue($value);
    }
}
