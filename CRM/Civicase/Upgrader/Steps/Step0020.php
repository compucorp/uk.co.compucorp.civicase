<?php

/**
 * Add custom fields.
 */
class CRM_Civicase_Upgrader_Steps_Step0020 {

  /**
   * Runs the upgrader changes.
   *
   * @return bool
   *   Return value in boolean.
   */
  public function apply() {
    try {
      (new CRM_Civicase_Setup_Manage_MembershipTypeCustomFieldManager())->create();
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
