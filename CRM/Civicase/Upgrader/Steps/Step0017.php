<?php

use CRM_Civicase_Setup_AddMyActivitiesMenu as AddMyActivitiesMenu;

/**
 * Adds My Activities Menu item..
 */
class CRM_Civicase_Upgrader_Steps_Step0017 {

  /**
   * Runs the upgrader changes.
   *
   * @return bool
   *   True when the upgrader runs successfully.
   */
  public function apply() {
    (new AddMyActivitiesMenu())->apply();

    return TRUE;
  }

}
