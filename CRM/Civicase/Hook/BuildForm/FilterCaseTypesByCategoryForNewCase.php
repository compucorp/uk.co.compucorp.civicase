<?php

use CRM_Civicase_Helper_CaseForm as CaseFormHelper;
use CRM_Civicase_Hook_Helper_CaseTypeCategory as CaseTypeCategoryHelper;

/**
 * Filter case types select field based on case category.
 */
class CRM_Civicase_Hook_BuildForm_FilterCaseTypesByCategoryForNewCase {

  /**
   * Filters the options for the case type select element based on the category.
   *
   * Updates the onchange attribute for the case type element.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   * @param string $formName
   *   Form Name.
   */
  public function run(CRM_Core_Form &$form, $formName) {
    if (!$this->shouldRun($form, $formName)) {
      return;
    }

    $this->filterCaseTypeOptionValues($form, $this->getCaseCategoryId($form));
  }

  /**
   * Filters the options for the case type select element based on the category.
   *
   * @param CRM_Core_Form $form
   *   Form class object.
   * @param string|int $caseCategoryId
   *   Case category Id.
   */
  private function filterCaseTypeOptionValues(CRM_Core_Form $form, $caseCategoryId) {
    $caseTypesInCategory = CaseTypeCategoryHelper::getCaseTypesForCategory($caseCategoryId);

    if (!$caseTypesInCategory) {
      $caseTypesInCategory = [];
    }

    $caseTypeIdElement = &$form->getElement('case_type_id');
    $options = $caseTypeIdElement->_options;

    foreach ($options as $key => $option) {
      $optionValue = $option['attr']['value'];
      if (!in_array($optionValue, $caseTypesInCategory) && $optionValue) {
        unset($options[$key]);
      }
    }

    sort($options);
    $caseTypeIdElement->_options = $options;
  }

  /**
   * Determines if the hook will run.
   *
   * The hook runs only on the New Case form (not when the case form is
   * used to delete or restore a case) and when the case category is valid.
   *
   * @param CRM_Core_Form $form
   *   Form class object.
   * @param string $formName
   *   Form name.
   *
   * @return bool
   *   returns a boolean to determine if hook will run or not.
   */
  private function shouldRun(CRM_Core_Form $form, $formName) {
    if (!CaseFormHelper::isNewCaseForm($formName, $form)) {
      return FALSE;
    }

    $caseCategoryId = $this->getCaseCategoryId($form);

    return $caseCategoryId && CaseTypeCategoryHelper::isValidCategory($caseCategoryId);
  }

  /**
   * Gets the Category Id for the case.
   *
   * @param CRM_Core_Form $form
   *   Form name.
   *
   * @return string|int|null
   *   Case category id.
   */
  private function getCaseCategoryId(CRM_Core_Form $form) {
    $urlParams = parse_url(htmlspecialchars_decode($form->controller->_entryURL), PHP_URL_QUERY);
    parse_str($urlParams, $urlParams);

    return !empty($urlParams['case_type_category']) ? $urlParams['case_type_category'] : CRM_Civicase_Helper_CaseCategory::getOptionValue();
  }

}
