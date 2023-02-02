<?php

use CRM_Civicase_Setup_Manage_CaseTypeCategoryFeaturesManager as CaseTypeCategoryManager;

/**
 * Update menus with new URL.
 */
class CRM_Civicase_Upgrader_Steps_Step0019 {

  /**
   * Runs the upgrader changes.
   *
   * @return bool
   *   Return value in boolean.
   */
  public function apply() {
    (new CaseTypeCategoryManager())->create();

    return TRUE;
  }

}
