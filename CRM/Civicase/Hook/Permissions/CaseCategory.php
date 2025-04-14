<?php

use CRM_Case_BAO_CaseType as CaseType;
use CRM_Civicase_ExtensionUtil as E;
use CRM_Civicase_Helper_CaseCategory as CaseCategoryHelper;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;

/**
 * Case Category Permissions hook.
 */
class CRM_Civicase_Hook_Permissions_CaseCategory {

  /**
   * Civi permissions array.
   *
   * @var array
   */
  private $permissions;

  /**
   * Case Category Permission service.
   *
   * @var \CRM_Civicase_Service_CaseCategoryPermission
   */
  private $permissionService;

  /**
   * CRM_Civicase_Hook_Permissions_CaseCategory constructor.
   *
   * @param array $permissions
   *   Permissions.
   */
  public function __construct(array &$permissions) {
    $this->permissions = &$permissions;
    $this->permissionService = new CaseCategoryPermission();
  }

  /**
   * Run function.
   */
  public function run() {
    $this->addPermissions();
  }

  /**
   * Adds permissions to the Civi permission array.
   */
  private function addPermissions() {
    $this->addCivicaseDefaultPermissions();
    $this->addCaseCategoryPermissions();
  }

  /**
   * Adds the default permissions proviced by Civicase.
   */
  private function addCivicaseDefaultPermissions() {
    $caseCategoryPermissions = $this->permissionService->get();
    $this->permissions[$caseCategoryPermissions['BASIC_CASE_CATEGORY_INFO']['name']] = [
      'label' => $caseCategoryPermissions['BASIC_CASE_CATEGORY_INFO']['label'],
      'description' => $caseCategoryPermissions['BASIC_CASE_CATEGORY_INFO']['description'],
    ];

    $this->permissions['Update cases with user role via webform'] = [
      'label' => E::ts('Update cases via webform where user has a case role'),
      'description' => E::ts('Users with this permission will be able to update a case via webform if their linked contact record has a current role on a case.'),
    ];
  }

  /**
   * Adds permissions provided by Case categories excluding Civicase.
   */
  private function addCaseCategoryPermissions() {
    $caseTypeCategories = CaseType::buildOptions('case_type_category', 'validate');
    if (empty($caseTypeCategories)) {
      return;
    }
    foreach ($caseTypeCategories as $caseTypeCategory) {
      if ($caseTypeCategory == CaseCategoryHelper::CASE_TYPE_CATEGORY_NAME) {
        continue;
      }

      $caseCategoryPermissions = $this->permissionService->get($caseTypeCategory);
      foreach ($caseCategoryPermissions as $caseCategoryPermission) {
        $this->permissions[$caseCategoryPermission['name']] = [
          'label' => $caseCategoryPermission['label'],
          'description' => $caseCategoryPermission['description'],
        ];
      }
    }
  }

}
