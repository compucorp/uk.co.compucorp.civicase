<?php

use Civi\Api4\OptionValue;

/**
 * Enables repeatable (multi-record) custom field sets for Cases.
 *
 * Sets `filter = 1` on the `cg_extend_objects` option values for the Case
 * entities (the core "Case" entity, every case category and the case-type
 * level entities). `filter` maps to `allow_is_multiple` in
 * CRM_Core_BAO_CustomGroup::getCustomGroupExtendsOptions(), which is what makes
 * the core Custom Group admin form expose the "Allow multiple records",
 * "Maximum records" and "Tab with table" options for Cases, in line with the
 * functionality already available for Contacts. See TCOSB-23 / ESE-404.
 *
 * Idempotent, so it is safe to run on both install and upgrade.
 */
class CRM_Civicase_Setup_EnableMultiRecordSupportForCaseCustomGroups {

  /**
   * Applies the change.
   *
   * @return bool
   *   TRUE on success.
   */
  public function apply(): bool {
    OptionValue::update(FALSE)
      ->addWhere('option_group_id:name', '=', 'cg_extend_objects')
      ->addWhere('name', 'IN', ['civicrm_case', 'civicrm_case_type'])
      ->addValue('filter', 1)
      ->execute();

    return TRUE;
  }

}
