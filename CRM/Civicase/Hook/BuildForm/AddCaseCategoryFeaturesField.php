<?php

use Civi\Api4\CaseCategoryFeatures;

/**
 * AddCaseCategoryFeaturesField BuildForm Hook Class.
 */
class CRM_Civicase_Hook_BuildForm_AddCaseCategoryFeaturesField extends CRM_Civicase_Hook_CaseCategoryFormHookBase {

  /**
   * Adds the Case Category Features Form field.
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

    $this->addCategoryFeaturesFormField($form);
    $this->addCategoryFeaturesTemplate();
  }

  /**
   * Adds the Case Category Features Form field.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   */
  private function addCategoryFeaturesFormField(CRM_Core_Form &$form) {
    $caseCategoryFeatures = new CRM_Civicase_Service_CaseTypeCategoryFeatures();
    $features = [];

    foreach ($caseCategoryFeatures->getFeatures() as $feature) {
      $features[] = 'case_category_feature_' . $feature['value'];
      $form->add(
        'checkbox',
        'case_category_feature_' . $feature['value'],
        $feature['label']
      );
    }

    $form->assign('features', $features);
    $this->setDefaultValues($form);
  }

  /**
   * Adds the template for case category features field template.
   */
  private function addCategoryFeaturesTemplate() {
    $templatePath = CRM_Civicase_ExtensionUtil::path() . '/templates';
    CRM_Core_Region::instance('page-body')->add(
      [
        'template' => "{$templatePath}/CRM/Civicase/Form/CaseCategoryFeatures.tpl",
      ]
    );
  }

  /**
   * Sets default values.
   */
  private function setDefaultValues(CRM_Core_Form &$form) {
    if (empty($form->getVar('_id'))) {
      return;
    }

    $defaults = $form->_defaultValues;
    $defaultFeatures = $this->getDefaultFeatures($form);
    $form->setDefaults(array_merge($defaults, $defaultFeatures));
  }

  /**
   * Returns the default value for the category instance fields.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   *
   * @return mixed|null
   *   Default value.
   */
  private function getDefaultFeatures(CRM_Core_Form $form) {
    $caseCategory = $form->getVar('_values')['value'];
    $enabledFeatures = [];

    $caseCategoryFeatures = CaseCategoryFeatures::get()
      ->addWhere('category_id', '=', $caseCategory)
      ->execute();

    foreach ($caseCategoryFeatures as $caseCategoryFeature) {
      $enabledFeatures['case_category_feature_' . $caseCategoryFeature['feature_id']] = 1;
    }

    return $enabledFeatures;
  }

}
