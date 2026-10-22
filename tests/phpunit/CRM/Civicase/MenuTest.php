<?php

use CRM_Civicase_ExtensionUtil as E;

/**
 * Tests the menu items defined in xml/Menu/civicase.xml.
 *
 * @group headless
 */
class CRM_Civicase_MenuTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Resets the permissions, so later tests are not restricted.
   */
  public function tearDown(): void {
    $this->resetPermissions();
  }

  /**
   * The case dashboard needs "access all" OR "access my" cases permission.
   */
  public function testCaseDashboardNeedsAllOrMyCasesPermission() {
    $menu = [];
    CRM_Core_Menu::read(E::path('xml/Menu/civicase.xml'), $menu);
    $accessArguments = $menu['civicrm/case/a']['access_arguments'];

    $this->assertEquals(
      [
        ['access all cases and activities', 'access my cases and activities'],
        'or',
      ],
      $accessArguments
    );

    $this->setPermissions(['access my cases and activities']);
    $this->assertTrue(CRM_Core_Permission::checkMenu($accessArguments[0], $accessArguments[1]));

    $this->setPermissions(['access all cases and activities']);
    $this->assertTrue(CRM_Core_Permission::checkMenu($accessArguments[0], $accessArguments[1]));

    $this->setPermissions(['basic case information']);
    $this->assertFalse(CRM_Core_Permission::checkMenu($accessArguments[0], $accessArguments[1]));
  }

}
