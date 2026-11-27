<?php

use CRM_Civicase_Helper_CaseForm as CaseFormHelper;
use CRM_Civicase_Service_CaseCategorySetting as CaseCategorySetting;
use CRM_Civicase_Hook_Helper_CaseTypeCategory as CaseTypeCategoryHelper;
use CRM_Civicase_Helper_NewCaseWebform as NewCaseWebformHelper;

/**
 * Fetches and redirects user to web form url for current case type category.
 */
class CRM_Civicase_Hook_PreProcess_CaseTypeCategoryWebFormRedirect {

  /**
   * Case category Setting.
   *
   * @var CRM_Civicase_Service_CaseCategorySetting
   *   CaseCategorySetting service.
   */
  private $caseCategorySetting;

  /**
   * Initialize dependencies.
   */
  public function __construct() {
    $this->caseCategorySetting = new CaseCategorySetting();
  }

  /**
   * Fetches and redirects user to web form url for current case type category.
   *
   * @param string $formName
   *   Form name.
   * @param CRM_Core_Form $form
   *   Form object.
   */
  public function run($formName, CRM_Core_Form &$form) {
    if (!$this->shouldRun($formName, $form)) {
      return;
    }
    $this->redirectToWebForm($form);
  }

  /**
   * Checks the form name, action and snippet parameter.
   *
   * The redirect only applies to the New Case form, not when the case form
   * is used to delete or restore a case.
   *
   * @param string $formName
   *   Form name.
   * @param CRM_Core_Form $form
   *   Form object.
   *
   * @return bool
   *   Whether this hook should run or not.
   */
  private function shouldRun($formName, CRM_Core_Form $form) {
    return (
      CaseFormHelper::isNewCaseForm($formName, $form) &&
      CRM_Utils_Array::value('snippet', $_GET, '') !== 'json'
    );
  }

  /**
   * Redirect to web form if available for current case type category.
   *
   * @param CRM_Core_Form $form
   *   Form object.
   */
  private function redirectToWebForm(CRM_Core_Form $form) {
    $caseCategoryId = CRM_Utils_Array::value('case_type_category', $_GET, CRM_Civicase_Helper_CaseCategory::getOptionValue());
    $caseTypeCategoryName = CRM_Civicase_Helper_CaseCategory::getCaseCategoryNameFromOptionValue($caseCategoryId);

    $webFormUrl = CaseTypeCategoryHelper::getNewCaseCategoryWebformUrl($caseTypeCategoryName, $this->caseCategorySetting);
    if (!$webFormUrl) {
      return;
    }

    $queryParams = ['reset' => 1];
    $webformClientId = NewCaseWebformHelper::getClientIdFromWebformUrl($webFormUrl);
    if ($webformClientId && $form->_currentlyViewedContactId) {
      $queryParams['cid' . $webformClientId] = $form->_currentlyViewedContactId;
    }

    CRM_Utils_System::redirect(CRM_Utils_System::url(trim($webFormUrl, '/'), $queryParams, FALSE));
  }

}
