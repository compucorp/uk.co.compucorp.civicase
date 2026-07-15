<?php

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
    CRM_Core_DAO::executeQuery('
      UPDATE civicrm_option_value ov
      INNER JOIN civicrm_option_group og ON og.id = ov.option_group_id
      SET ov.filter = 1
      WHERE og.name = %1
        AND ov.name IN (%2, %3)
        AND (ov.filter IS NULL OR ov.filter <> 1)
    ', [
      1 => ['cg_extend_objects', 'String'],
      2 => ['civicrm_case', 'String'],
      3 => ['civicrm_case_type', 'String'],
    ]);

    return TRUE;
  }

}
