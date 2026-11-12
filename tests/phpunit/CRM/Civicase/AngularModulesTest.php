<?php

/**
 * Tests the extension's Angular module declarations (ang/*.ang.php).
 *
 * @group headless
 */
class CRM_Civicase_AngularModulesTest extends BaseHeadlessTest {

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

}
