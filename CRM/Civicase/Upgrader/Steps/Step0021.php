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
   * @return bool
   *   Return value in boolean.
   */
  public function apply(): bool {
    try {
      (new EnableMultiRecordSupportForCaseCustomGroups())->apply();
    }
    catch (\Throwable $th) {
      \Civi::log()->error('Error upgrading Civicase', [
        'context' => [
          'backtrace' => $th->getTraceAsString(),
          'message' => $th->getMessage(),
        ],
      ]);
    }

    return TRUE;
  }

}
