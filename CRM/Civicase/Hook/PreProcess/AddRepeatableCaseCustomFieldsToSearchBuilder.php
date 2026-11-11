<?php

use CRM_Civicase_Service_RepeatableCaseCustomSearchFields as RepeatableCaseCustomSearchFields;

/**
 * Makes repeatable Case custom fields available in Search Builder.
 */
class CRM_Civicase_Hook_PreProcess_AddRepeatableCaseCustomFieldsToSearchBuilder {

  /**
   * Adds the repeatable Case fields to the Case search fields.
   *
   * @param string $formName
   *   Form name.
   * @param CRM_Core_Form $form
   *   Form object.
   */
  public function run($formName, &$form) {
    if (!$this->shouldRun($formName)) {
      return;
    }

    $repeatableFields = RepeatableCaseCustomSearchFields::getFields();
    if (empty($repeatableFields)) {
      return;
    }

    $caseFields = CRM_Case_BAO_Case::exportableFields();
    CRM_Case_BAO_Case::$_exportableFields = $caseFields + $repeatableFields;
  }

  /**
   * Determines if the hook should run.
   *
   * @param string $formName
   *   Form name.
   *
   * @return bool
   *   TRUE for Search Builder when the user can access CiviCase.
   */
  private function shouldRun($formName) {
    return $formName === CRM_Contact_Form_Search_Builder::class && CRM_Core_Permission::access('CiviCase');
  }

}
