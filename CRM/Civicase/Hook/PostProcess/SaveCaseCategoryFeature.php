<?php

/**
 * CaseCategoryFeature Post Process Hook class.
 */

use Civi\Api4\CaseCategoryFeatures;

/**
 * SaveCaseCategoryFeature PostProcess hook.
 */
class CRM_Civicase_Hook_PostProcess_SaveCaseCategoryFeature extends CRM_Civicase_Hook_CaseCategoryFormHookBase {

  /**
   * Saves the case category feature type relationship.
   *
   * @param string $formName
   *   The Form class name.
   * @param CRM_Core_Form $form
   *   The Form instance.
   */
  public function run($formName, CRM_Core_Form $form) {
    if (!$this->shouldRun($form, $formName)) {
      return;
    }

    $caseCategoryValues = $form->getVar('_submitValues');
    $caseCategory = $caseCategoryValues['value'];

    $this->saveCaseCategoryFeature($caseCategory, $caseCategoryValues);
  }

  /**
   * Saves the case category instance values.
   *
   * @param int $categoryId
   *   Case category id.
   * @param array $submittedValues
   *   The key-value pair of submitted values.
   */
  private function saveCaseCategoryFeature($categoryId, array $submittedValues) {
    // Delete old features link.
    CaseCategoryFeatures::delete()
      ->addWhere('category_id', '=', $categoryId)
      ->execute();

    // Create new features link.
    $caseCategoryFeatures = new CRM_Civicase_Service_CaseTypeCategoryFeatures();
    foreach ($caseCategoryFeatures->getFeatures() as $feature) {
      if (!empty($submittedValues['case_category_feature_' . $feature['value']])) {
        CaseCategoryFeatures::create()
          ->addValue('category_id', $categoryId)
          ->addValue('feature_id', $feature['value'])
          ->execute();
      }
    }

  }

}
