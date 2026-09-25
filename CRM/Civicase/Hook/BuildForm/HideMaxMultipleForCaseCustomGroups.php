<?php

/**
 * Hides "Maximum number of multiple records" for Case custom field sets.
 */
class CRM_Civicase_Hook_BuildForm_HideMaxMultipleForCaseCustomGroups {

  /**
   * Adds the script and the list of Case `extends` values to the form.
   *
   * @param CRM_Core_Form $form
   *   Form object.
   * @param string $formName
   *   Form name.
   */
  public function run(CRM_Core_Form &$form, $formName) {
    if (!$this->shouldRun($formName)) {
      return;
    }

    CRM_Core_Resources::singleton()
      ->addVars('civicase', ['caseCustomGroupExtends' => $this->getCaseExtends()])
      ->addScriptFile('uk.co.compucorp.civicase', 'js/hide-case-custom-group-max-multiple.js');
  }

  /**
   * Returns the custom group `extends` values that refer to Cases.
   *
   * @return string[]
   *   The core "Case" value plus every case category value.
   */
  private function getCaseExtends(): array {
    $extends = ['Case'];
    foreach (CRM_Core_BAO_CustomGroup::getCustomGroupExtendsOptions() as $option) {
      if (($option['table_name'] ?? NULL) === 'civicrm_case') {
        $extends[] = $option['id'];
      }
    }

    return array_values(array_unique($extends));
  }

  /**
   * Determines if the hook should run.
   *
   * @param string $formName
   *   Form name.
   *
   * @return bool
   *   TRUE for the Custom Field Set admin form.
   */
  private function shouldRun($formName) {
    return $formName === CRM_Custom_Form_Group::class;
  }

}
