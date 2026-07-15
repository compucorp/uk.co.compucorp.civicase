<?php

use CRM_Civicase_Setup_EnableMultiRecordSupportForCaseCustomGroups as EnableMultiRecordSupportForCaseCustomGroups;

/**
 * Enables repeatable (multi-record) custom field sets for Cases.
 *
 * Runs the shared setup routine on upgrade. The same routine also runs on a
 * fresh install (see CRM_Civicase_Upgrader::install()). See TCOSB-23 / ESE-404.
 */
class CRM_Civicase_Upgrader_Steps_Step0021 {

  /**
   * Performs Upgrade.
   *
   * Exceptions are intentionally allowed to propagate so a failed upgrade is
   * surfaced by the upgrade queue rather than silently marked as successful.
   *
   * @return bool
   *   TRUE on success.
   */
  public function apply(): bool {
    (new EnableMultiRecordSupportForCaseCustomGroups())->apply();

    return TRUE;
  }

}
