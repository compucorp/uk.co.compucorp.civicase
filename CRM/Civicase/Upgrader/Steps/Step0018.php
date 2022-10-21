<?php

/**
 * Changes custom_group entity_column_value column type to 'text'.
 *
 * Changes the type of civicrm_custom_group.extends_entity_column_value
 * column to text from the default varchar(255).
 * Because the column grows quickly as its used in CiviCase
 * to store every case_category sub_types using the custom group.
 */
class CRM_Civicase_Upgrader_Steps_Step0018 {

  /**
   * Runs the upgrader changes.
   *
   * @return bool
   *   True when the upgrader runs successfully.
   */
  public function apply() {
    $tableName = CRM_Core_DAO_CustomGroup::$_tableName;
    CRM_Core_DAO::executeQuery("ALTER TABLE $tableName MODIFY `extends_entity_column_value` TEXT DEFAULT NULL COMMENT 'linking custom group for dynamic object'", [], TRUE, NULL, FALSE, FALSE);

    if (CRM_Core_Config::singleton()->logging) {
      $logging = new CRM_Logging_Schema();
      $logging->fixSchemaDifferencesFor($tableName, ['MODIFY' => 'extends_entity_column_value']);
    }

    return TRUE;
  }

}
