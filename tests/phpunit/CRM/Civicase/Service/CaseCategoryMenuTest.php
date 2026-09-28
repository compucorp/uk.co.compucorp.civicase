<?php

use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;
use CRM_Civicase_Service_CaseCategoryMenu as CaseCategoryMenuService;
use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;
use CRM_Civicase_Helper_Category as CategoryHelper;

/**
 * Test class for the CRM_Civicase_Service_CaseCategoryMenu.
 *
 * @group headless
 */
class CRM_Civicase_Service_CaseCategoryMenuTest extends BaseHeadlessTest {

  /**
   * Instance of CaseCategoryMenu service.
   *
   * @var CRM_Civicase_Service_CaseCategoryMenu
   */
  private $caseCategoryMenu;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->caseCategoryMenu = new CaseCategoryMenuService();
  }

  /**
   * Test createItems method adds the expected menus.
   */
  public function testCreateNewItemsAddsExpectedMenus() {
    $caseCategory = CaseCategoryFabricator::fabricate();

    $this->caseCategoryMenu->createItems([
      'name' => $caseCategory['name'],
      'label' => $caseCategory['label'],
      'singular_label' => $caseCategory['label'],
    ]);

    $this->assertMenuCreatedForCaseCategory($caseCategory);
  }

  /**
   * Test calling twice createItems does not create duplicates.
   */
  public function testCreateTwiceSameItemsDoesNotCreateDuplicates() {
    $caseCategory = CaseCategoryFabricator::fabricate();

    // First call.
    $this->caseCategoryMenu->createItems([
      'name' => $caseCategory['name'],
      'label' => $caseCategory['label'],
      'singular_label' => $caseCategory['label'],
    ]);
    // Second call.
    $this->caseCategoryMenu->createItems([
      'name' => $caseCategory['name'],
      'label' => $caseCategory['label'],
      'singular_label' => $caseCategory['label'],
    ]);

    $menuCreatedCount = civicrm_api3('Navigation', 'getcount', ['name' => $caseCategory['name']]);
    $this->assertEquals(1, $menuCreatedCount);
  }

  /**
   * Test createItems method assigns same weight to different menus.
   */
  public function testCreateTwoDifferentMenusAssignsSameWeight() {
    $caseCategoryOne = CaseCategoryFabricator::fabricate();
    $caseCategoryTwo = CaseCategoryFabricator::fabricate();
    $expectWeightForMenu = $this->getExpectedWeightForCategoryMenu();

    $this->caseCategoryMenu->createItems([
      'name' => $caseCategoryOne['name'],
      'label' => $caseCategoryOne['label'],
      'singular_label' => $caseCategoryOne['label'],
    ]);
    // This clears the cache.
    civicrm_api3('Navigation', 'getfields', ['cache_clear' => 1]);
    $this->caseCategoryMenu->createItems([
      'name' => $caseCategoryTwo['name'],
      'label' => $caseCategoryTwo['label'],
      'singular_label' => $caseCategoryTwo['label'],
    ]);

    $menuOneWeight = civicrm_api3('Navigation', 'getsingle',
      [
        'name' => $caseCategoryOne['name'],
        'return' => ['weight'],
      ]
    )['weight'];
    $menuTwoWeight = civicrm_api3('Navigation', 'getsingle',
      [
        'name' => $caseCategoryTwo['name'],
        'return' => ['weight'],
      ]
    )['weight'];
    $this->assertEquals($expectWeightForMenu, $menuOneWeight);
    $this->assertEquals($expectWeightForMenu, $menuTwoWeight);
  }

  /**
   * Test deleteItems method removes menus and submenus.
   */
  public function testDeleteItemsRemovesMenusAndSubMenus() {
    $caseCategory = CaseCategoryFabricator::fabricate();

    $this->caseCategoryMenu->createItems([
      'name' => $caseCategory['name'],
      'label' => $caseCategory['label'],
      'singular_label' => $caseCategory['label'],
    ]);
    $menuCreatedId = civicrm_api3('Navigation', 'getsingle',
      [
        'name' => $caseCategory['name'],
        'return' => ['id'],
      ]
    )['id'];

    $this->caseCategoryMenu->deleteItems($caseCategory['name']);

    $menuCreatedCount = civicrm_api3('Navigation', 'getcount', ['id' => $menuCreatedId]);
    $subMenusCreatedCount = civicrm_api3('Navigation', 'getcount', ['parent_id' => $menuCreatedId]);
    $this->assertEquals(0, $menuCreatedCount);
    $this->assertEquals(0, $subMenusCreatedCount);
  }

  /**
   * Test updateItems method produces expected changes.
   */
  public function testUpdateItemsProducesExpectedChanges() {
    $caseCategory = CaseCategoryFabricator::fabricate();
    $newValues = [
      'icon' => 'new icon',
      'is_active' => 0,
    ];

    $this->caseCategoryMenu->createItems([
      'name' => $caseCategory['name'],
      'label' => $caseCategory['label'],
      'singular_label' => $caseCategory['label'],
    ]);
    $menuCreated = civicrm_api3('Navigation', 'getsingle', ['name' => $caseCategory['name']]);
    foreach ($newValues as $key => $value) {
      $this->assertNotEquals($value, $menuCreated[$key]);
    }

    $this->caseCategoryMenu->updateItems($caseCategory['id'], $newValues);
    $menuCreated = civicrm_api3('Navigation', 'getsingle', ['name' => $caseCategory['name']]);
    foreach ($newValues as $key => $value) {
      $this->assertEquals($value, $menuCreated[$key]);
    }
  }

  /**
   * Test that resetCaseCategorySubmenusUrl method update the URLs as expected.
   *
   * This code is not actually asserting that the menu content was modified,
   * but it is enough to check that final URLs are as expected.
   */
  public function testMenusLinksAreCorrectlyUpdated() {
    $caseCategory = CaseCategoryFabricator::fabricate();
    $submenus = $this->caseCategoryMenu->getSubmenus(
      CategoryHelper::get($caseCategory['name'])
    );

    // Create the menus but with different URL.
    foreach ($submenus as $submenu) {
      $submenu['url'] = 'Random string ' . rand();
      civicrm_api3('Navigation', 'create', $submenu);
    }

    // Assert that the URL are different to the expected.
    foreach ($submenus as $submenu) {
      $newUrl = civicrm_api3('Navigation', 'getsingle', [
        'name' => $submenu['name'],
        'return' => ['url'],
      ])['url'];
      $this->assertNotEquals($submenu['url'], $newUrl);
    }

    // Perform the update.
    $this->caseCategoryMenu->resetCaseCategorySubmenusUrl(
      CategoryHelper::get($caseCategory['name'])
    );
    civicrm_api3('Navigation', 'getfields', ['cache_clear' => 1]);

    // Assert that the URL are as expected.
    foreach ($submenus as $submenu) {
      $newUrl = civicrm_api3('Navigation', 'getsingle', [
        'name' => $submenu['name'],
        'return' => ['url'],
      ])['url'];
      $this->assertEquals($submenu['url'], $newUrl);
    }
  }

  /**
   * Assert the menu created for the given category has expected information.
   */
  private function assertMenuCreatedForCaseCategory(array $caseCategory) {
    $menuCreated = civicrm_api3('Navigation', 'getsingle', ['name' => $caseCategory['name']]);

    $this->assertEquals(ts($caseCategory['name']), $menuCreated['name']);
    $expectedCategoryLabel = ucfirst(strtolower($caseCategory['label']));
    $this->assertEquals($expectedCategoryLabel, $menuCreated['label']);
    $this->assertEquals(1, $menuCreated['is_active']);
    $this->assertEquals($this->getPermissionForNavigationMenu($caseCategory['name']), $menuCreated['permission']);
    $this->assertEquals('OR', $menuCreated['permission_operator']);
    $this->assertEquals($this->getExpectedWeightForCategoryMenu(), $menuCreated['weight']);

    $subMenusCreatedCount = civicrm_api3('Navigation', 'getcount', ['parent_id' => $menuCreated['id']]);
    $this->assertEquals(6, $subMenusCreatedCount);
  }

  /**
   * Returns permissions that the menu should have.
   */
  private function getPermissionForNavigationMenu(string $caseTypeCategoryName) {
    $permissions = (new CaseCategoryPermission())->get($caseTypeCategoryName);

    return sprintf(
      "%s,%s",
      $permissions['ACCESS_MY_CASE_CATEGORY_AND_ACTIVITIES']['name'],
      $permissions['ACCESS_CASE_CATEGORY_AND_ACTIVITIES']['name']
    );
  }

  /**
   * Get the expected weight for the category menu.
   */
  private static function getExpectedWeightForCategoryMenu() {
    return CRM_Core_DAO::getFieldValue(
        'CRM_Core_DAO_Navigation',
        'Cases',
        'weight',
        'name'
      ) + 1;
  }

}
