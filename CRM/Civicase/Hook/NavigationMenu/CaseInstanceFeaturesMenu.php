<?php

use CRM_Certificate_ExtensionUtil as E;
use Civi\Api4\CaseCategoryFeatures;
use Civi\Api4\OptionGroup;

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

      $caseInstancesGroup = $this->retrieveCaseInstanceWithEnabledFeatures();

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

  /**
   * Retrieves case instance that has the defined features enabled.
   *
   * @return array
   *   Array of Key\Pair value grouped by case instance id.
   */
  private function retrieveCaseInstanceWithEnabledFeatures() {
    $caseInstanceGroup = OptionGroup::get()->addWhere('name', '=', 'case_type_categories')->execute()[0] ?? NULL;

    if (empty($caseInstanceGroup)) {
      return [];
    }

    $result = CaseCategoryFeatures::get()
      ->addSelect('*', 'option_value.label', 'option_value.name', 'feature_id:name', 'feature_id:label', 'navigation.id')
      ->addJoin('OptionValue AS option_value', 'LEFT',
      ['option_value.value', '=', 'category_id']
    )
      ->addJoin('Navigation AS navigation', 'LEFT',
      ['navigation.name', '=', 'option_value.name']
    )
      ->addWhere('option_value.option_group_id', '=', $caseInstanceGroup['id'])
      ->addWhere('feature_id:name', 'IN', self::FEATURES_WITH_MENU)
      ->execute();

    $caseCategoriesGroup = array_reduce((array) $result, function (array $accumulator, array $element) {
      $accumulator[$element['category_id']]['items'][] = $element;
      $accumulator[$element['category_id']]['navigation_id'] = $element['navigation.id'];
      $accumulator[$element['category_id']]['name'] = $element['option_value.name'];

      return $accumulator;
    }, []);

    return $caseCategoriesGroup;
  }

}
