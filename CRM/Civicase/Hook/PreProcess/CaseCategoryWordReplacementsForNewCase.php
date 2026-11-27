<?php

use CRM_Civicase_Helper_CaseCategory as CaseCategoryHelper;
use CRM_Civicase_Helper_CaseForm as CaseFormHelper;
use CRM_Civicase_Hook_Helper_CaseTypeCategory as CaseTypeCategoryHelper;

/**
 * Class CRM_Civicase_Hook_PreProcess_CaseCategoryWordReplacements.
 */
class CRM_Civicase_Hook_PreProcess_CaseCategoryWordReplacementsForNewCase {

  /**
   * Adds the word replacements array to Civi's translation locale.
   *
   * @param string $formName
   *   Form Name.
   * @param CRM_Core_Form $form
   *   Form Class object.
   */
  public function run($formName, CRM_Core_Form &$form) {
    if (!$this->shouldRun($formName, $form)) {
      return;
    }

    $this->addWordReplacements($form, $this->getCaseCategoryId($form));
  }

  /**
   * Adds the word replacements array to Civi's translation locale.
   *
   * This will make Civi automatically translate form labels that are
   * displayed using the ts function.
   *
   * @param CRM_Core_Form $form
   *   Page class.
   * @param string $caseCategoryId
   *   Case category Id.
   */
  private function addWordReplacements(CRM_Core_Form $form, $caseCategoryId) {
    $caseCategoryName = CRM_Civicase_Helper_CaseCategory::getCaseCategoryNameFromOptionValue($caseCategoryId);
    CRM_Civicase_Hook_Helper_CaseTypeCategory::addWordReplacements($caseCategoryName);
    // We need to translate this manually as Civi does not the page title
    // through the ts function.
    $pageTitle = $form->get_template_vars('activityType');
    CRM_Utils_System::setTitle(CaseTypeCategoryHelper::translate($pageTitle));
    CaseCategoryHelper::updateBreadcrumbs($caseCategoryId);
  }

  /**
   * Gets the Case Category Name under consideration.
   *
   * @param CRM_Core_Form $form
   *   Form name.
   *
   * @return string|int|null
   *   case category Id.
   */
  private function getCaseCategoryId(CRM_Core_Form $form) {
    $urlParams = parse_url(htmlspecialchars_decode($form->controller->_entryURL), PHP_URL_QUERY);
    parse_str($urlParams, $urlParams);

    return !empty($urlParams['case_type_category']) ? $urlParams['case_type_category'] : CRM_Civicase_Helper_CaseCategory::getOptionValue();
  }

  /**
   * Determines if the hook will run.
   *
   * The hook runs only on the New Case form (not when the case form is
   * used to delete or restore a case, where core doesn't assign the
   * activity type used for the page title) and when the case category is
   * valid.
   *
   * @param string $formName
   *   Form name.
   * @param CRM_Core_Form $form
   *   Form object.
   *
   * @return bool
   *   returns a boolean to determine if hook will run or not.
   */
  private function shouldRun($formName, CRM_Core_Form $form) {
    if (!CaseFormHelper::isNewCaseForm($formName, $form)) {
      return FALSE;
    }

    $caseCategoryId = $this->getCaseCategoryId($form);

    return $caseCategoryId && CaseTypeCategoryHelper::isValidCategory($caseCategoryId);
  }

}
