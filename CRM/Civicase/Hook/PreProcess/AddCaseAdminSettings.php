<?php

use CRM_Civicase_Service_CaseCategorySetting as CaseCategorySetting;

/**
 * Class CRM_Civicase_Hook_PreProcess_AddCaseAdminSettings.
 */
class CRM_Civicase_Hook_PreProcess_AddCaseAdminSettings {

  /**
   * Sets the case admin settings.
   *
   * @param string $formName
   *   Form name.
   * @param CRM_Core_Form $form
   *   Form object class.
   */
  public function run($formName, CRM_Core_Form &$form) {
    if (!$this->shouldRun($formName)) {
      return;
    }

    $settings = $form->getVar('_settings');

    $this->setCaseCategoryWebformSettings($form, $settings);
    $this->addCivicaseSettingsToForm($settings);
    $form->setVar('_settings', $settings);
  }

  /**
   * Takes civicase setting names and adds them to the admin form.
   *
   * The settings are taken from the civicase settings file. This function is
   * needed to properly display these settings on the form.
   *
   * @param array $settings
   *   Settings array.
   */
  private function addCivicaseSettingsToForm(array &$settings) {
    $civicaseSettings = $this->getCiviCaseSettings();
    $settingKeys = array_keys($civicaseSettings);

    foreach ($settingKeys as $settingKey) {
      $settings[$settingKey] = CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME;
    }
  }

  /**
   * Returns the list of settings defined in the civicase settings file.
   *
   * @return array
   *   The civicase settings.
   */
  private function getCiviCaseSettings() {
    $civicasePath = (new CRM_Extension_System())
      ->getFullContainer()
      ->getPath('uk.co.compucorp.civicase');

    return require $civicasePath . '/settings/CiviCase.setting.php';
  }

  /**
   * Sets webform settings for case categories.
   *
   * @param CRM_Core_Form $form
   *   Form object class.
   * @param array $settings
   *   Settings array.
   */
  private function setCaseCategoryWebformSettings(CRM_Core_Form &$form, array &$settings) {
    $caseSetting = new CaseCategorySetting();
    $caseCategoryWebFormSetting = $caseSetting->getForWebform();

    $form->assign('caseCategoryWebFormSetting', $caseCategoryWebFormSetting);
  }

  /**
   * Determines if the hook will run.
   *
   * @param string $formName
   *   Form class object.
   *
   * @return bool
   *   returns TRUE or FALSE.
   */
  private function shouldRun($formName) {
    return $formName == 'CRM_Admin_Form_Setting_Case';
  }

}
