<?php

use CRM_Civicase_Hook_Permissions_CaseCategory as CaseCategoryPermissionsHook;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;
use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;

/**
 * Tests for the case category permissions hook.
 *
 * @group headless
 */
class CRM_Civicase_Hook_Permissions_CaseCategoryTest extends BaseHeadlessTest {

  /**
   * CaseType classes whose cached field lists the tests change.
   */
  const CASE_TYPE_CLASSES = ['CRM_Case_DAO_CaseType', 'CRM_Case_BAO_CaseType'];

  /**
   * {@inheritDoc}
   */
  protected function tearDown(): void {
    foreach (self::CASE_TYPE_CLASSES as $class) {
      unset(Civi::$statics[$class]['fields'], Civi::$statics[$class]['fieldKeys']);
    }

    parent::tearDown();
  }

  /**
   * Tests the permissions of each case category are defined.
   */
  public function testDefinesPermissionsForEachCategory() {
    $category = CaseCategoryFabricator::fabricate();

    $this->assertCategoryPermissionsDefined($category['name'], $this->runHook());
  }

  /**
   * Tests the permissions of an inactive case category are defined.
   */
  public function testDefinesPermissionsForInactiveCategory() {
    $category = CaseCategoryFabricator::fabricate(['is_active' => 0]);

    $this->assertCategoryPermissionsDefined($category['name'], $this->runHook());
  }

  /**
   * Tests permissions are defined when CaseType has no category field.
   *
   * This is the state during a CiviCRM upgrade, which does not run
   * hook_civicrm_entityTypes, so CaseType's cached fields lack the
   * case_type_category field Civicase adds.
   */
  public function testDefinesPermissionsWhenCaseTypeCategoryFieldIsMissing() {
    $category = CaseCategoryFabricator::fabricate();
    $this->removeCaseTypeCategoryField();

    $this->assertArrayNotHasKey('case_type_category', CRM_Case_BAO_CaseType::fields());
    $this->assertCategoryPermissionsDefined($category['name'], $this->runHook());
  }

  /**
   * Runs the hook on an empty permission list.
   *
   * @return array
   *   The permissions the hook defined.
   */
  private function runHook() {
    $permissions = [];
    (new CaseCategoryPermissionsHook($permissions))->run();

    return $permissions;
  }

  /**
   * Removes case_type_category from CaseType's cached fields.
   */
  private function removeCaseTypeCategoryField() {
    foreach (self::CASE_TYPE_CLASSES as $class) {
      $class::fields();
      unset(Civi::$statics[$class]['fields']['case_type_category'], Civi::$statics[$class]['fieldKeys']);
    }
  }

  /**
   * Asserts every permission of a case category is defined.
   *
   * @param string $categoryName
   *   Case category name.
   * @param array $permissions
   *   Permissions defined by the hook.
   */
  private function assertCategoryPermissionsDefined($categoryName, array $permissions) {
    foreach ((new CaseCategoryPermission())->get($categoryName) as $permission) {
      $this->assertArrayHasKey($permission['name'], $permissions);
    }
  }

}
