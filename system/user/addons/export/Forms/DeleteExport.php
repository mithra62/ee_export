<?php

namespace Mithra62\Export\Forms;

use ExpressionEngine\Library\CP\Form\AbstractForm;
use ExpressionEngine\Library\CP\Form;

/**
 * Confirmation form shown before an Export configuration is permanently removed.
 *
 * Renders a single yes_no toggle named 'confirm'. Delete.php checks that the
 * submitted value is 'y' before actually calling ->delete() on the model.
 */
class DeleteExport extends AbstractForm
{
    public function generate(): array
    {
        $form = new Form;

        $form->getGroup('export_delete_form_heading')
            ->getFieldSet('export_delete_confirm')
                ->setDesc('export_delete_confirm_desc')
                ->getField('confirm', 'yes_no');

        return $form->toArray();
    }
}
