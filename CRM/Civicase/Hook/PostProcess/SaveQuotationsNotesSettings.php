<?php

/**
 * Save Quotations Note field.
 */
class CRM_Civicase_Hook_PostProcess_SaveQuotationsNotesSettings {

  /**
   * Saves the Quotations Note Form field.
   *
   * @param string $formName
   *   Form Name.
   * @param CRM_Core_Form $form
   *   Form Class object.
   */
  public function run($formName, CRM_Core_Form $form) {
    if (!$this->shouldRun($formName)) {
      return;
    }

    $this->saveQuotationsNoteField($form);
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
   * Saves the Quotations Note Form field.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   */
  public function saveQuotationsNoteField(CRM_Core_Form &$form) {
    $values = $form->getVar('_submitValues');
    if (!empty($values['quotations_notes'])) {
      Civi::settings()->set('quotations_notes', $values['quotations_notes']);
    }
  }

}
