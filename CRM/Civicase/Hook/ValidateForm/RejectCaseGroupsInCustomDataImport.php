<?php

use CRM_Civicase_Hook_BuildForm_HideCaseGroupsFromCustomDataImport as HideCaseGroups;

/**
 * Rejects a Case group submitted to core's "Import Multi-value Custom Data".
 */
class CRM_Civicase_Hook_ValidateForm_RejectCaseGroupsInCustomDataImport {

  /**
   * Adds a form error when the chosen group extends Cases.
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
    if ($formName !== HideCaseGroups::FORM_NAME || !is_array($fields)) {
      return;
    }

    $groupId = (int) ($fields[HideCaseGroups::ELEMENT_NAME] ?? 0);
    if (!$groupId || !in_array($groupId, HideCaseGroups::getCaseGroupIds(), TRUE)) {
      return;
    }

    $errors[HideCaseGroups::ELEMENT_NAME] = ts('Case custom data cannot be imported here. Use CSV Import to API with the CaseCustomImporter entity instead.');
  }

}
