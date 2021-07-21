<?php

/**
 * Adds My Activities Menu item.
 */
class CRM_Civicase_Setup_AddMyActivitiesMenu {

  /**
   * My Activities URL.
   */
  const MY_ACTIVITIES_URL = 'civicrm/case/a/#/myactivities';

  /**
   * Adds My Activities Menu item.
   */
  public function apply() {
    $this->addMenuLink();
  }

  /**
   * Adds My Activities Menu item.
   */
  private function addMenuLink() {
    civicrm_api3('Navigation', 'create', [
      'is_active' => 1,
      'parent_id' => 'user-menu-ext__user-menu',
      'permission' => 'access CiviCRM backend and API',
      'label' => 'My Activities',
      'name' => 'civicase__my-activities__menu',
      'url' => self::MY_ACTIVITIES_URL,
      'icon' => 'fa fa-check',
    ]);
  }

}
