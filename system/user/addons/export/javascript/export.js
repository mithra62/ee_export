/**
 * Export addon — CP form dynamic behaviours
 *
 * Loaded via ee()->cp->load_package_js('export') which queues this file through
 * EE's combo loader. jQuery is available as `jQuery` (EE runs no-conflict mode).
 * EE.Export.ajax_url and EE.CSRF_TOKEN are set globally before this runs.
 */
jQuery(function ($) {
    'use strict';

    // ── AJAX helper ───────────────────────────────────────────────────────────

    function ajaxPost(data, cb) {
        data.CSRF_TOKEN = EE.CSRF_TOKEN;
        $.ajax({
            url:      EE.Export.ajax_url,
            type:     'POST',
            data:     data,
            dataType: 'json',
            success:  cb,
            error:    function (xhr, status, err) {
                console.error('[Export]', status, err);
            }
        });
    }

    // ── Channel → field population ────────────────────────────────────────────

    /**
     * Wires a channel <select> so that changing it fires an AJAX request and
     * repopulates the associated field <select> with matching fields.
     *
     * @param {string} channelSel  jQuery selector for the channel select
     * @param {string} fieldSel    jQuery selector for the field select to repopulate
     * @param {string} fieldType   EE field type slug ('grid' or 'fluid_field')
     */
    function wireChannelToField(channelSel, fieldSel, fieldType) {
        $(document).on('change', channelSel, function () {
            var channelId = $(this).val();
            var $field    = $(fieldSel);

            if (! channelId) {
                $field.empty().append($('<option>', { value: '', text: EE.lang.export_select_channel_first || '— Select a channel first —' }));
                return;
            }

            ajaxPost({ action: 'fields', channel_id: channelId, field_type: fieldType }, function (fields) {
                $field.empty();

                if ($.isEmptyObject(fields)) {
                    $field.append($('<option>', { value: '', text: EE.lang.export_select_none || '— Select —' }));
                    return;
                }

                $.each(fields, function (fieldId, label) {
                    $field.append($('<option>', { value: fieldId, text: label }));
                });

                // After repopulating fields the column list may have changed too
                refreshColumns();
            });
        });
    }

    wireChannelToField('[name="src_grid_channel"]',  '[name="src_grid_field"]',  'grid');
    wireChannelToField('[name="src_fluid_channel"]', '[name="src_fluid_field"]', 'fluid_field');

    // ── Column picker ─────────────────────────────────────────────────────────

    var $picker = $('.export-col-picker');
    var $checks = $('.export-col-checkboxes');

    /**
     * Determine the channel_id and field_id for the currently active source.
     */
    function activeSourceIds() {
        var source = $('[name="source"]').val();
        var channelId = 0;
        var fieldId   = 0;

        if (source === 'entries') {
            channelId = $('[name="src_entries_channel"]').val() || 0;
        } else if (source === 'grid') {
            channelId = $('[name="src_grid_channel"]').val() || 0;
            fieldId   = $('[name="src_grid_field"]').val()   || 0;
        } else if (source === 'fluid') {
            channelId = $('[name="src_fluid_channel"]').val() || 0;
            fieldId   = $('[name="src_fluid_field"]').val()   || 0;
        }

        return { channel_id: channelId, field_id: fieldId };
    }

    /**
     * Sync the checked checkboxes into the appropriate hidden input based on
     * the current col_mode radio value.
     */
    function updateHiddenCols() {
        var mode   = $('[name="col_mode"]:checked').val();
        var values = [];

        $checks.find('input[type="checkbox"]:checked').each(function () {
            values.push($(this).val());
        });

        var joined = values.join('|');

        if (mode === 'whitelist') {
            $('#export_fields_val').val(joined);
            $('#export_exclude_val').val('');
        } else if (mode === 'blacklist') {
            $('#export_fields_val').val('');
            $('#export_exclude_val').val(joined);
        } else {
            $('#export_fields_val').val('');
            $('#export_exclude_val').val('');
        }
    }

    /**
     * Load column checkboxes via AJAX for the current source + channel/field,
     * pre-checking any columns already stored in the hidden inputs.
     */
    function refreshColumns() {
        var mode = $('[name="col_mode"]:checked').val();
        if (mode === 'all') { return; }

        var source = $('[name="source"]').val();
        var ids    = activeSourceIds();

        // Read the currently-stored pipe-sep values to pre-check the boxes
        var storedWhitelist = $('#export_fields_val').val().split('|').filter(Boolean);
        var storedBlacklist = $('#export_exclude_val').val().split('|').filter(Boolean);
        var preChecked      = mode === 'whitelist' ? storedWhitelist : storedBlacklist;

        $checks.html('<em style="color:#888">Loading…</em>');

        ajaxPost({
            action:     'columns',
            source:     source,
            channel_id: ids.channel_id,
            field_id:   ids.field_id
        }, function (columns) {
            $checks.empty();

            if (! columns || ! columns.length) {
                $checks.html('<em style="color:#888">' + (EE.lang.export_no_columns_available || 'Select a channel (and field for Grid/Fluid) to load available columns.') + '</em>');
                return;
            }

            $.each(columns, function (i, col) {
                var isChecked = preChecked.indexOf(col) !== -1;
                var $label    = $('<label>', { style: 'display:block; margin-bottom:4px' });
                var $cb       = $('<input>', {
                    type:    'checkbox',
                    value:   col,
                    checked: isChecked,
                    style:   'margin-right:6px'
                });
                $label.append($cb).append(document.createTextNode(col));
                $checks.append($label);
            });

            // Wire up live sync
            $checks.find('input[type="checkbox"]').on('change', updateHiddenCols);

            // Sync hidden fields to reflect the current checkbox state
            updateHiddenCols();
        });
    }

    // col_mode radio toggle
    $(document).on('change', '[name="col_mode"]', function () {
        var mode = $(this).val();
        if (mode === 'all') {
            $picker.hide();
            $('#export_fields_val').val('');
            $('#export_exclude_val').val('');
        } else {
            $picker.show();
            refreshColumns();
        }
    });

    // Refresh columns when the source or any channel/field select changes
    $(document).on('change', '[name="source"]',               refreshColumns);
    $(document).on('change', '[name="src_entries_channel"]',  refreshColumns);
    $(document).on('change', '[name="src_grid_channel"]',     refreshColumns);
    $(document).on('change', '[name="src_grid_field"]',       refreshColumns);
    $(document).on('change', '[name="src_fluid_channel"]',    refreshColumns);
    $(document).on('change', '[name="src_fluid_field"]',      refreshColumns);

    // ── Page-load initial state ───────────────────────────────────────────────

    var initialMode = $('[name="col_mode"]:checked').val();
    if (initialMode && initialMode !== 'all') {
        $picker.show();
        refreshColumns();
    } else {
        $picker.hide();
    }
});
