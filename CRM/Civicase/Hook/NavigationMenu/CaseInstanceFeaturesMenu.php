<?php

use CRM_Certificate_ExtensionUtil as E;
use CRM_Civicase_Service_CaseTypeCategoryFeatures as CaseTypeCategoryFeatures;

/**
 * Add menu for enabled features on Case instnace menus.
 */
class CRM_Civicase_Hook_NavigationMenu_CaseInstanceFeaturesMenu extends CRM_Civicase_Hook_NavigationMenu_AbstractMenuAlter {

  /**
   * Features menu should be created for.
   *
   * @var array
   */
  const FEATURES_WITH_MENU = [
    'quotations',
  ];

  /**
   * Modifies the navigation menu.
   *
   * @param array $menu
   *   Menu Array.
   */
  public function run(array &$menu) {
    $this->addFeaturesMenu($menu);
  }

  /**
   * Adds enabled features menu to menu.
   *
   * @param array $menu
   *   Tree of menu items, per hook_civicrm_navigationMenu.
   */
  private function addFeaturesMenu(array &$menu) {
    try {
      $caseTypeCategoryFeatures = new CaseTypeCategoryFeatures();
      $caseInstancesGroup = $caseTypeCategoryFeatures->retrieveCaseInstanceWithEnabledFeatures(self::FEATURES_WITH_MENU);

      foreach ($caseInstancesGroup as $caseInstances) {
        $separator = 0;
        $caseInstanceMenu = &$menu[$caseInstances['navigation_id']];
        $caseInstanceName = $caseInstances['name'];
        $caseInstanceName = ($caseInstanceName === 'Prospecting') ? 'prospect' : $caseInstanceName;
        $desiredWeight = $this->moveMenuDown($caseInstanceMenu['child'], "manage_{$caseInstanceName}_workflows");

        foreach ($caseInstances['items'] as $caseInstance) {
          $caseInstanceMenu['child'][] = [
            'attributes' => [
              'label' => ts('Manage ' . $caseInstance['feature_id:label']),
              'name' => 'Manage ' . $caseInstance['feature_id:label'],
              'url' => "civicrm/case-features/a?case_type_category={$caseInstance['category_id']}#/{$caseInstance['feature_id:name']}",
              'permission' => $caseInstanceMenu['attributes']['permission'],
              'operator' => 'OR',
              'parentID' => $caseInstanceMenu['attributes']['navID'],
              'active' => 1,
              'separator' => $separator++,
              'weight' => $desiredWeight,
            ],
          ];
        }
      }
    }
    catch (\Throwable $th) {
      \Civi::log()->error(E::ts("Error adding case instance features menu"), [
        'context' => [
          'backtrace' => $th->getTraceAsString(),
          'message' => $th->getMessage(),
        ],
      ]);
    }
  }

}
