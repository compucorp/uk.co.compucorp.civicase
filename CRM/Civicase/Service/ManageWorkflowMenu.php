<?php

use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;
use CRM_Civicase_Service_CaseCategoryInstance as CaseCategoryInstance;
use CRM_Civicase_Helper_CaseCategory as CaseCategoryHelper;
use CRM_Civicase_Service_CaseCategoryMenu as CaseCategoryMenu;
use CRM_Civicase_Helper_Category as CategoryHelper;

/**
 * Create/Delete Case Type Category Menu items.
 */
class CRM_Civicase_Service_ManageWorkflowMenu {

  /**
   * Creates Manage Workflow menu for existing case categories.
   *
   * @param string $instanceTypeName
   *   Case category instance type name..
   * @param bool $showCategoryNameOnMenuLabel
   *   Flag for using the case type category name on the menu label.
   * @param string $parentMenuLabel
   *   Name of the existing parent menu label.
   */
  public function create(string $instanceTypeName, bool $showCategoryNameOnMenuLabel, string $parentMenuLabel = NULL) {
    $caseTypeCategories = CaseCategoryHelper::getCaseCategories();

    $instanceObj = new CaseCategoryInstance();
    $instances = $instanceObj->getCaseCategoryInstances($instanceTypeName);

    foreach ($caseTypeCategories as $caseTypeCategory) {
      $isInstanceCaseCategory = NULL;

      foreach ($instances as $instance) {
        if ($instance->category_id == $caseTypeCategory['value']) {
          $isInstanceCaseCategory = $instance;
          break;
        }
      }

      if (!$isInstanceCaseCategory) {
        continue;
      }

      $parentMenuForCaseCategory = civicrm_api3('Navigation', 'get', [
        'sequential' => 1,
        'label' => $parentMenuLabel ? $parentMenuLabel : $caseTypeCategory['name'],
      ])['values'][0];

      $menuLabel = $showCategoryNameOnMenuLabel
        ? 'Manage ' . $caseTypeCategory['name']
        : 'Manage Workflows';

      if (!empty($parentMenuForCaseCategory['id'])) {
        $this->addSeparatorToTheLastMenuOf(
          $parentMenuForCaseCategory['id']
        );
        $this->createItemInto(
          $parentMenuForCaseCategory['id'],
          $caseTypeCategory['name'],
          $menuLabel
        );
      }
    }
  }

  /**
   * Creates Manage Workflow menu for the given parent id.
   *
   * @param string $parentId
   *   Id of the parent menu item.
   * @param string $caseTypeCategoryName
   *   Case Type Category name.
   * @param string $menuLabel
   *   Label for the menu to be added.
   */
  private function createItemInto($parentId, $caseTypeCategoryName, string $menuLabel) {
    $caseCategoryPermission = new CaseCategoryPermission();
    $permissions = $caseCategoryPermission->get($caseTypeCategoryName);

    $menuExists = civicrm_api3('Navigation', 'getcount', [
      'name' => 'manage_' . $caseTypeCategoryName . '_workflows',
    ]) > 0;

    if (!$menuExists) {
      civicrm_api3('Navigation', 'create', [
        'parent_id' => $parentId,
        'url' => $this->getUrlForCategory($caseTypeCategoryName),
        'label' => $menuLabel,
        'name' => 'manage_' . $caseTypeCategoryName . '_workflows',
        'is_active' => TRUE,
        'permission' => "{$permissions['ADMINISTER_CASE_CATEGORY']['name']}, administer CiviCRM",
        'permission_operator' => 'OR',
      ]);
    }
  }

  /**
   * Get Workflow Url for the case category.
   *
   * @param string $categoryName
   *   Case type category name.
   *
   * @return string
   *   Url of the workflow item.
   */
  private function getUrlForCategory(string $categoryName) {
    $submenus = (new CaseCategoryMenu())->getSubmenus(
      CategoryHelper::get($categoryName)
    );
    $submenus = array_column($submenus, 'url', 'name');

    return $submenus["manage_{$categoryName}_workflows"];
  }

  /**
   * Add separator to the last child menu of the given parent id.
   *
   * @param string $parentId
   *   Id of the parent menu item.
   */
  private function addSeparatorToTheLastMenuOf($parentId) {
    $childMenuItemWithMaxWeight = civicrm_api3('Navigation', 'get', [
      'sequential' => 1,
      'parent_id' => $parentId,
      'options' => ['limit' => 1, 'sort' => "weight DESC"],
    ])['values'][0];

    civicrm_api3('Navigation', 'create', [
      'id' => $childMenuItemWithMaxWeight['id'],
      'has_separator' => 1,
    ]);
  }

}
