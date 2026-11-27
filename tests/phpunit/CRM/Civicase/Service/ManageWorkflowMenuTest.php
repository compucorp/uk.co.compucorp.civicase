<?php

use CRM_Civicase_Service_ManageWorkflowMenu as ManageWorkflowMenu;
use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;
use CRM_Civicase_Test_Fabricator_CaseCategoryInstance as CaseCategoryInstanceFabricator;
use CRM_Civicase_Test_Fabricator_CaseCategoryInstanceType as CaseCategoryInstanceTypeFabricator;

/**
 * Test class for the CRM_Civicase_Service_ManageWorkflowMenu.
 *
 * @group headless
 */
class CRM_Civicase_Service_ManageWorkflowMenuTest extends BaseHeadlessTest {

  /**
   * Instance of ManageWorkflowMenu service.
   *
   * @var CRM_Civicase_Service_ManageWorkflowMenu
   */
  private $manageWorkflowMenu;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->manageWorkflowMenu = new ManageWorkflowMenu();
  }

  /**
   * Test create method creates expected submenus.
   *
   * @param bool $showCategoryNameOnMenuLabel
   *   Value for the second parameter of tested method.
   *
   * @dataProvider getDataForMenu
   */
  public function testCreateWorkflowMenuItems(bool $showCategoryNameOnMenuLabel) {
    $caseCategory = CaseCategoryFabricator::fabricate();
    $caseCategoryInstanceType = CaseCategoryInstanceTypeFabricator::fabricate();
    CaseCategoryInstanceFabricator::fabricate([
      'category_id' => $caseCategory['value'],
      'instance_id' => $caseCategoryInstanceType['value'],
    ]);
    $menuCreated = $this->createMainMenuWithFirstSubItem($caseCategory['name']);

    $this->manageWorkflowMenu->create($caseCategoryInstanceType['name'], $showCategoryNameOnMenuLabel);

    $subMenuCreated = civicrm_api3('Navigation', 'getsingle', [
      'name' => 'manage_' . $caseCategory['name'] . '_workflows',
    ]);
    $menuLabel = $showCategoryNameOnMenuLabel
      ? 'Manage ' . $caseCategory['name']
      : 'Manage Workflows';
    $this->assertEquals($menuLabel, $subMenuCreated['label']);
    $parentMenu = civicrm_api3('Navigation', 'getsingle', ['id' => $subMenuCreated['parent_id']]);
    $this->assertEquals($menuCreated['id'], $parentMenu['id']);
  }

  /**
   * Creates the expected menus for a given category name.
   *
   * It creates the main menu, and a first submenu assigned to it. This is
   * required for correctly testing create method.
   *
   * @param string $caseCategoryName
   *   Case Type Category name.
   */
  private function createMainMenuWithFirstSubItem(string $caseCategoryName) {
    $menuCreated = civicrm_api3('Navigation', 'create', ['label' => $caseCategoryName]);
    civicrm_api3('Navigation', 'create',
      [
        'parent_id' => $menuCreated['id'],
        'label' => 'First subitem',
      ]
    );

    return $menuCreated;
  }

  /**
   * Data provider for returning options for create method.
   *
   * Returns the two possible options for the boolean flag.
   */
  public function getDataForMenu() {
    return [
      [TRUE],
      [FALSE],
    ];
  }

}
