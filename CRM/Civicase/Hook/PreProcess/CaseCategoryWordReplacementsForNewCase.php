<?php

use CRM_Civicase_Helper_CaseCategory as CaseCategoryHelper;
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
    $caseCategoryId = $this->getCaseCategoryId($form);

    if (!$this->shouldRun($formName)) {
      return;
    }

    if (!CaseTypeCategoryHelper::isValidCategory($caseCategoryId)) {
      return;
    }

    $this->addWordReplacements($form, $caseCategoryId);
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
    $labelsBefore = $this->getOpenCaseFieldLabels($form);
    CRM_Civicase_Hook_Helper_CaseTypeCategory::addWordReplacements($caseCategoryName);
    $this->translateOpenCaseFieldLabels($form, $labelsBefore);
    // We need to translate this manually as Civi does not the page title
    // through the ts function.
    $pageTitle = $form->get_template_vars('activityType');
    CRM_Utils_System::setTitle(CaseTypeCategoryHelper::translate($pageTitle));
    CaseCategoryHelper::updateBreadcrumbs($caseCategoryId);
  }

  /**
   * The labels CiviCRM gives the new case form's own fields, as worded now.
   *
   * Since CiviCRM 6.20 the form defines these fields (the start date, the
   * details and others) in its own preProcess, which runs before this hook:
   * their labels are translated before the category's wording is added.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   *
   * @return array
   *   Labels by field name. Empty on a CiviCRM that has no such fields.
   */
  private function getOpenCaseFieldLabels(CRM_Core_Form $form) {
    if (!is_callable([CRM_Case_Form_Activity_OpenCase::class, 'setFields'])) {
      return [];
    }
    // setFields() only fills in _fields: the form's own, which another
    // extension may have changed, are put back.
    $fields = $form->_fields ?? NULL;
    CRM_Case_Form_Activity_OpenCase::setFields($form);
    $labels = [];
    foreach ((array) $form->_fields as $fieldName => $field) {
      if (isset($field['label'])) {
        $labels[$fieldName] = $field['label'];
      }
    }
    $form->_fields = $fields;

    return $labels;
  }

  /**
   * Gives the new case form's own fields the category's wording.
   *
   * A label is replaced only when it is still the one CiviCRM gave it, so a
   * label set by another extension is left alone and no label is translated
   * twice.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   * @param array $labelsBefore
   *   The labels from getOpenCaseFieldLabels() before the wording was added.
   */
  private function translateOpenCaseFieldLabels(CRM_Core_Form $form, array $labelsBefore) {
    if (empty($labelsBefore) || empty($form->_fields) || !is_array($form->_fields)) {
      return;
    }
    $labelsNow = $this->getOpenCaseFieldLabels($form);
    foreach ($labelsBefore as $fieldName => $labelBefore) {
      if (isset($labelsNow[$fieldName], $form->_fields[$fieldName]['label'])
        && $form->_fields[$fieldName]['label'] === $labelBefore) {
        $form->_fields[$fieldName]['label'] = $labelsNow[$fieldName];
      }
    }
  }

  /**
   * Gets the Case Category Name under consideration.
   *
   * @param CRM_Core_Form $form
   *   Form name.
   *
   * @return string|null
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
   * @param string $formName
   *   Form name.
   *
   * @return bool
   *   returns a boolean to determine if hook will run or not.
   */
  private function shouldRun($formName) {
    return $formName == CRM_Case_Form_Case::class;
  }

}
