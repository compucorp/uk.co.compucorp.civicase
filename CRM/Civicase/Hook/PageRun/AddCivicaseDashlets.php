<?php

use Civi\Angular\AngularLoader;

/**
 * Loads Civicase Angular dashlets when viewing the dashboard page.
 */
class CRM_Civicase_Hook_PageRun_AddCivicaseDashlets {

  /**
   * Runs the hook.
   *
   * @param object $page
   *   Page Object.
   */
  public function run($page) {
    if (!$this->shouldRun($page)) {
      return;
    }

    CRM_Core_Resources::singleton()
      ->addScriptFile('uk.co.compucorp.civicase', 'packages/moment.min.js');

    $loader = new AngularLoader();
    $loader->setModules(['civicase']);
    $loader->load();
  }

  /**
   * Determines if the hook should run.
   *
   * @param object $page
   *   Page Object.
   *
   * @return bool
   *   True when viewing the dashboard page.
   */
  private function shouldRun($page) {
    return get_class($page) === CRM_Contact_Page_DashBoard::class;
  }

}
