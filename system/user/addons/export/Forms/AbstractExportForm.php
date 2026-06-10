<?php

namespace Mithra62\Export\Forms;

use ExpressionEngine\Library\CP\Form;
use ExpressionEngine\Library\CP\Form\AbstractForm;

/**
 * Base class for all Export CP form objects.
 *
 * Extends EE's AbstractForm (which declares generate(): array as the contract)
 * and adds a single shared factory helper so every concrete form class gets a
 * fresh Form instance through the same call rather than each importing `new Form`
 * independently.
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
}
