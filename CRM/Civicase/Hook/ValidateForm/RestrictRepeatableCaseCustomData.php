<?php

use CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAccess as CaseCustomDataAccess;

/**
 * Rejects case custom data edits to repeatable Case groups without access.
 *
 * Edit access to a group means "access all custom data" or an ACL granting
 * "Edit" on it.
 *
 * QuickForms save custom data via CRM_Core_BAO_CustomValueTable::postProcess,
 * not the API, so civi.api.authorize does not see them. The only QuickForm
 * that saves repeatable Case custom data is civicrm/case/cd/edit
 * (CRM_Case_Form_CustomData). Other case forms (e.g. Open Case) may render an
 * Inline repeatable group's fields, but only register non-multi-record fields
 * (addCustomDataFieldsToForm), so those values are never saved and must not
 * block the form.
 */
class CRM_Civicase_Hook_ValidateForm_RestrictRepeatableCaseCustomData {

  /**
   * Adds a form error when a gated group is edited without edit access.
   *
   * Every submitted gated field counts, including blank ones: on this form
   * clearing a value is itself an edit.
   *
   * @param string $formName
   *   Form name.
   * @param array $fields
   *   Submitted values.
   * @param array $files
   *   Uploaded files.
   * @param CRM_Core_Form $form
   *   Form object.
   * @param array $errors
   *   Form errors (by reference).
   */
  public function run($formName, &$fields, &$files, &$form, &$errors) {
    if ($formName !== 'CRM_Case_Form_CustomData' || !is_array($fields)) {
      return;
    }
    $deniedKeys = CaseCustomDataAccess::getUnmanageableCustomParamKeys($fields);
    if (empty($deniedKeys)) {
      return;
    }

    // Any key fails validation (CRM_Core_Form::validate counts all errors);
    // keying on the field shows the message next to it, and body.tpl lists it
    // at the top of the form either way.
    $errors[reset($deniedKeys)] = ts('You do not have permission to add, edit or delete these records.');
  }

}
