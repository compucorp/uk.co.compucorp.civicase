<?php

use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * Tests the extension's Angular module declarations (ang/*.ang.php).
 *
 * @group headless
 */
class CRM_Civicase_AngularModulesTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * {@inheritDoc}
   */
  public function tearDown(): void {
    unset($_GET['q'], $_REQUEST['case_type_category']);
    $this->resetPermissions();
    $this->unregisterCurrentLoggedInContactFromSession();
    parent::tearDown();
  }

  /**
   * The modules can be collected again in the same process.
   */
  public function testModulesCanBeCollectedAgainAfterTheCacheIsCleared() {
    $angular = Civi::service('angular');
    $angular->getModules();
    $angular->clear();

    $modules = $angular->getModules();

    foreach (['civicase-base', 'civicase', 'civicase-features', 'workflow', 'my-activities'] as $name) {
      $this->assertArrayHasKey($name, $modules);
    }
  }

  /**
   * The civicase module doesn't depend on who rebuilds it, or from where.
   *
   * Core caches the module list for every user and URL, so the request that
   * rebuilds it must not fail, or change it, because of its user or URL.
   */
  public function testCivicaseModuleIsTheSameWhenUserWithoutCategoryAccessRebuildsIt() {
    $caseCategory = CaseCategoryFabricator::fabricate();
    CRM_Core_PseudoConstant::flush();
    $angular = Civi::service('angular');
    $angular->clear();
    $expectedModule = $angular->getModules()['civicase'];

    $this->registerCurrentLoggedInContactInSession(ContactFabricator::fabricate()['id']);
    $this->setPermissions(['access CiviCRM', 'access all cases and activities']);
    $_GET['q'] = 'civicrm/case/a';
    $_REQUEST['case_type_category'] = $caseCategory['value'];
    unset(Civi::$statics['CRM_Core_Region']);
    $angular->clear();

    $module = $angular->getModules()['civicase'];

    $this->assertEquals($expectedModule, $module);
    $pageSettings = CRM_Core_Region::instance('html-header')->get('settings')['settings'] ?? [];
    $this->assertArrayNotHasKey('user_contact_id', $pageSettings['config'] ?? []);
  }

}
