<?php

/**
 * Helpers for the core case form (CRM_Case_Form_Case).
 */
class CRM_Civicase_Helper_CaseForm {

  /**
   * Checks if the form is the core "New Case" form.
   *
   * CRM_Case_Form_Case is also used to delete and restore cases, but core
   * only builds the case fields (case type, status, etc.), assigns the
   * activity type and sets the page title when adding a case. Hooks that
   * rely on any of these should only run for the add action.
   *
   * @param string $formName
   *   Form name.
   * @param CRM_Core_Form $form
   *   Form object.
   *
   * @return bool
   *   TRUE when the form is being used to add a new case.
   */
  public static function isNewCaseForm($formName, CRM_Core_Form $form) {
    return $formName === CRM_Case_Form_Case::class
      && (int) $form->getAction() === CRM_Core_Action::ADD;
  }

}
