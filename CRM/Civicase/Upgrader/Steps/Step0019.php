<?php

use CRM_Civicase_Setup_Manage_CaseSalesOrderStatusManager as CaseSalesOrderStatusManager;
use CRM_Civicase_Setup_Manage_CaseTypeCategoryFeaturesManager as CaseTypeCategoryManager;
use CRM_Civicase_Setup_Manage_MembershipTypeCustomFieldManager as MembershipTypeCustomFieldManager;
use CRM_Civicase_Setup_Manage_QuotationTemplateManager as QuotationTemplateManager;

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
    try {
      CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, CRM_Civicase_ExtensionUtil::path('sql/auto_install.sql'));

      (new QuotationTemplateManager())->create();
      (new CaseTypeCategoryManager())->create();
      (new CaseSalesOrderStatusManager())->create();
      (new MembershipTypeCustomFieldManager())->create();
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
