<?php

/**
 * Adds the My Activities Feed dashlet to the dashboard.
 */
class CRM_Civicase_Upgrader_Steps_Step0015 {

  /**
   * Runs the upgrader changes.
   *
   * @return bool
   *   Return TRUE when the upgrader runs successfully.
   */
  public function apply() {
    civicrm_api3('Dashboard', 'create', [
      'name' => 'myactivitiesfeed',
      'label' => 'My Activities Feed',
      'url' => '',
      'fullscreen_url' => '',
      'permission' => 'access all cases and activities,access my cases and activities',
      'permission_operator' => 'OR',
      'is_active' => '1',
      'is_reserved' => '1',
      'cache_minutes' => '7200',
      'directive' => 'civicase-my-activities-feed-dashlet',
    ]);

    return TRUE;
  }

}
