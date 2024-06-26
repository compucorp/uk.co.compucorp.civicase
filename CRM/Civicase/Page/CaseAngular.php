<?php

/**
 * Class CRM_Civicase_Page_CaseAngular.
 *
 * Define an Angular base-page for CiviCase.
 */
class CRM_Civicase_Page_CaseAngular extends \CRM_Core_Page {

  /**
   * Run Function.
   *
   * This function takes care of all the things common to all
   * pages. This typically involves assigning the appropriate
   * smarty variable :)
   *
   * @return string
   *   The content generated by running this page
   */
  public function run() {
    $loader = Civi::service('angularjs.loader');
    $loader->setPageName('civicrm/case/a');
    $loader->addModules(['crmApp', 'civicase', 'civicase-features']);
    \Civi::resources()->addSetting([
      'crmApp' => [
        'defaultRoute' => '/case/list',
      ],
    ]);
    return parent::run();
  }

  /**
   * Get Template File Name.
   *
   * @inheritdoc
   */
  public function getTemplateFileName() {
    return 'Civi/Angular/Page/Main.tpl';
  }

}
