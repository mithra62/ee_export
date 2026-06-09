<?php
/**
 * Export CP — Create / Edit form view
 *
 * Variables provided:
 *   $sections              — form sections for ee:_shared/form
 *   $base_url              — form action URL string
 *   $save_btn_text         — submit button label
 *   $save_btn_text_working — submit button "working" label
 *   $cp_page_title         — heading string
 *   $current_source        — currently selected source key
 *   $current_format        — currently selected format key
 *   $current_output        — currently selected output key
 *   $ajax_url              — URL for AJAX endpoints
 */
?>
<?php $this->embed('ee:_shared/form') ?>

<script>
(function ($) {
$(function () {
    'use strict';

    var ajaxUrl = '<?= $ajax_url ?>';

    // ── Date inputs ───────────────────────────────────────────────────────────
    // EE's _shared/form has no native 'date' field type, so we render 'text'
    // and upgrade to type="date" here (browser renders a native date picker).
    // jQuery silently blocks .prop('type', ...) on existing inputs, so we set
    // the DOM property directly via this.type inside .each().
    $(
        'input.export-date-field,' +
        'input[name="src_members_join_start"],' +
        'input[name="src_members_join_end"],' +
        'input[name="src_members_last_login_start"],' +
        'input[name="src_members_last_login_end"]'
    ).each(function () { this.type = 'date'; });

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Show fieldset rows whose data-group matches prefix+activeValue;
     * hide all others sharing the same prefix.
     * EE's _shared/form wraps each row that carries a `group` key in:
     *   <fieldset data-group="{value}">…</fieldset>
     */
    function showGroup(prefix, activeValue) {
        $('[data-group^="' + prefix + '"]').each(function () {
            $(this).toggle($(this).data('group') === prefix + activeValue);
        });
    }

    // ── Source toggle ─────────────────────────────────────────────────────────

    var $sourceSelect = $('select[name="source"]');
    if ($sourceSelect.length) {
        showGroup('source_', $sourceSelect.val());
        $sourceSelect.on('change', function () {
            showGroup('source_', $(this).val());
            refreshColumns();
        });
    }

    // ── Format toggle ─────────────────────────────────────────────────────────

    var $formatSelect = $('select[name="format"]');
    if ($formatSelect.length) {
        showGroup('format_', $formatSelect.val());
        $formatSelect.on('change', function () {
            showGroup('format_', $(this).val());
        });
    }

    // ── Output toggle (show/hide local path row) ──────────────────────────────

    var $outputSelect = $('select[name="output"]');
    if ($outputSelect.length) {
        $('[data-group="output_local"]').toggle($outputSelect.val() === 'local');
        $outputSelect.on('change', function () {
            $('[data-group="output_local"]').toggle($(this).val() === 'local');
        });
    }

    // ── Channel → field population (Grid / Fluid) ─────────────────────────────

    function wireChannelToField(sourceKey, fieldType) {
        var $channelSel = $('select[name="src_' + sourceKey + '_channel"]');
        var $fieldSel   = $('select[name="src_' + sourceKey + '_field"]');

        if (! $channelSel.length || ! $fieldSel.length) { return; }

        $channelSel.on('change', function () {
            var channelId = $(this).val();
            if (! channelId) {
                $fieldSel.html('<option value=""><?= lang('export_select_channel_first') ?></option>');
                return;
            }
            ajaxPost({ action: 'fields', channel_id: channelId, field_type: fieldType }, function (data) {
                var html = '<option value=""><?= lang('export_select_none') ?></option>';
                $.each(data, function (fid, label) {
                    html += '<option value="' + fid + '">' + label + '</option>';
                });
                $fieldSel.html(html);
                refreshColumns();
            });
        });
    }

    wireChannelToField('grid',  'grid');
    wireChannelToField('fluid', 'fluid_field');

    // ── Column picker ─────────────────────────────────────────────────────────

    function currentColMode() {
        return $('input[name="col_mode"]:checked').val() || 'all';
    }

    var $picker     = $('.export-col-picker');
    var $checkboxes = $('.export-col-checkboxes');

    $('input[name="col_mode"]').on('change', function () {
        $picker.toggle($(this).val() !== 'all');
        if ($(this).val() !== 'all') { refreshColumns(); }
    });

    function refreshColumns() {
        var mode = currentColMode();
        if (mode === 'all' || ! $checkboxes.length) { return; }

        var source = $sourceSelect.val() || '';
        var params = { action: 'columns', source: source };

        // Gather relevant inputs from within the active source group rows
        $('[data-group="source_' + source + '"] select, [data-group="source_' + source + '"] input[type="text"]').each(function () {
            var name = $(this).attr('name');
            if (name) {
                var key = name.replace('src_' + source + '_', '');
                params[key] = $(this).val();
                if (key === 'channel') { params.channel_id = $(this).val(); }
                if (key === 'field')   { params.field_id   = $(this).val(); }
            }
        });

        ajaxPost(params, function (columns) {
            if (! $.isArray(columns) || columns.length === 0) {
                $checkboxes.html('<em><?= lang('export_no_columns_available') ?></em>');
                return;
            }

            var whitelist = ($('#export_fields_val').val()  || '').split('|').filter(Boolean);
            var blacklist = ($('#export_exclude_val').val() || '').split('|').filter(Boolean);
            var selected  = mode === 'whitelist' ? whitelist : blacklist;

            var html = '';
            $.each(columns, function (i, col) {
                var checked = $.inArray(col, selected) !== -1 ? ' checked' : '';
                html += '<label class="export-checkbox-label" style="display:inline-block;margin:.25em .75em .25em 0">'
                      + '<input type="checkbox" class="export-col-check" value="' + col + '"' + checked + '> '
                      + col + '</label>';
            });

            $checkboxes.html(html);
            $checkboxes.find('.export-col-check').on('change', updateHiddenCols);
        });
    }

    function updateHiddenCols() {
        var mode    = currentColMode();
        var checked = [];
        $checkboxes.find('.export-col-check:checked').each(function () {
            checked.push($(this).val());
        });

        if (mode === 'whitelist') {
            $('#export_fields_val').val(checked.join('|'));
            $('#export_exclude_val').val('');
        } else {
            $('#export_exclude_val').val(checked.join('|'));
            $('#export_fields_val').val('');
        }
    }

    // ── Modifier rows ─────────────────────────────────────────────────────────

    $(document).on('click', '.export-add-modifier', function (e) {
        e.preventDefault();
        var $btn     = $(this);
        var rowCount = parseInt($btn.data('rowCount'), 10) || 0;
        var $tbody   = $('#export-modifier-rows');
        if (! $tbody.length) { return; }

        $tbody.append(
            '<tr class="export-modifier-row">'
          + '<td><input type="text" name="modify[' + rowCount + '][column]" class="form-control" placeholder="column_name"></td>'
          + '<td><input type="text" name="modify[' + rowCount + '][chain]"  class="form-control" placeholder="ee_date[%Y-%m-%d]|uc_first"></td>'
          + '<td><a href="#" class="export-remove-modifier button button--small button--default"><?= lang('export_remove') ?></a></td>'
          + '</tr>'
        );

        $btn.data('rowCount', rowCount + 1);
    });

    $(document).on('click', '.export-remove-modifier', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    // ── AJAX helper ───────────────────────────────────────────────────────────

    function ajaxPost(data, callback) {
        data.csrf_token = EE.CSRF_TOKEN;
        $.ajax({
            url:      ajaxUrl,
            type:     'POST',
            data:     data,
            dataType: 'json',
            success:  callback,
            error: function (xhr, status, err) {
                console.error('Export AJAX error:', status, err);
            }
        });
    }

    // ── Init: apply visible groups and refresh column picker if needed ────────

    if (currentColMode() !== 'all') {
        refreshColumns();
    }

});
})(jQuery);
</script>
