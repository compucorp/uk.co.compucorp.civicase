<?php

/**
 * Add Quotations Note field.
 */
class CRM_Civicase_Hook_BuildForm_AddQuotationsNotesToContributionSettings {

  /**
   * Adds the Quotations Note Form field.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   * @param string $formName
   *   Form Name.
   */
  public function run(CRM_Core_Form &$form, $formName) {
    if (!$this->shouldRun($formName)) {
      return;
    }

    $this->addQuotationsNoteField($form);
  }

  /**
   * Checks if this shook should run.
   *
   * @param string $formName
   *   Form Name.
   *
   * @return bool
   *   True if the hook should run.
   */
  public function shouldRun($formName) {
    return $formName == CRM_Admin_Form_Preferences_Contribute::class;
  }

  /**
   * Add Quotations note fields.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   */
  public function addQuotationsNoteField(CRM_Core_Form &$form) {
    $fieldName = 'quotations_notes';
    $field = [
      $fieldName => [
        'html_type' => 'wysiwyg',
        'title' => ts('Terms/Notes for Quotations'),
        'weight' => 5,
        'description' => ts('Enter note or message to be displyaed on quotations'),
        'attributes' => ['rows' => 2, 'cols' => 40],
      ],
    ];

    $form->add('wysiwyg', $fieldName, $field[$fieldName]['title'], $field[$fieldName]['attributes']);
    $form->assign('htmlFields', array_merge($form->get_template_vars('htmlFields'), $field));
    $value = Civi::settings()->get($fieldName) ?? NULL;
    $form->setDefaults(array_merge($form->_defaultValues, [$fieldName => $value]));
  }

}
