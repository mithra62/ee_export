<?php
/**
 * Export CP — Index view
 *
 * Variables provided:
 *   $table      — viewData array from ee('CP/Table')->viewData()
 *   $create_url — URL string for the "Create Export" button
 */
?>
<div class="app-notice-wrap"><?= ee('CP/Alert')->getAllInlines() ?></div>

<div class="tbl-ctrls">
    <fieldset class="tbl-search right">
        <a class="button button--primary" href="<?= $create_url ?>"><?= lang('export_create_new') ?></a>
    </fieldset>
    <?php $this->embed('ee:_shared/table', $table) ?>
</div>
