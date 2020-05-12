<?php

class CRM_Civicase_Hook_alterContent_CivicaseSettingsForm {

  public function run(&$content, $context, $templateName, $form) {
    if (!$this->shouldRun($form)) {
      return;
    }

    $settingsTemplate = &CRM_Core_Smarty::singleton();
    $settingsTemplateHtml = $settingsTemplate->fetchWith('CRM/Civicase/Admin/Form/Settings.tpl', []);

    $doc = phpQuery::newDocumentHTML($content);
    $doc->find('table.form-layout tr:last')->append($settingsTemplateHtml);

    $content = $doc->getDocument();
  }

  private function shouldRun($form) {
    $isViewingTheCaseAdminForm = get_class($form) === CRM_Admin_Form_Setting_Case::class;

    return false;
  }

}
