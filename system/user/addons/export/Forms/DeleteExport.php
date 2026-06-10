<?php

namespace Mithra62\Export\Forms;

/**
 * Confirmation form shown before an Export configuration is permanently removed.
 *
 * Renders a single yes_no toggle named 'confirm'. Delete.php checks that the
 * submitted value is 'y' before actually calling ->delete() on the model.
 */
class DeleteExport extends AbstractExportForm
{
    public function generate(): array
    {
        $form = $this->makeForm();

        $form->getGroup('export_delete_form_heading')
            ->getFieldSet('export_delete_confirm')
            ->setDesc('export_delete_confirm_desc')
            ->getField('confirm', 'yes_no');

        return $form->toArray();
    }
}
