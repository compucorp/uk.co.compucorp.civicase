<?php

/**
 * Custom Field Options Filter class.
 */
class CRM_Civicase_APIHelpers_CustomFieldFilter {

  /**
   * Handles filtering of case custom fields with multiple options.
   *
   * @param CRM_Utils_SQL_Select $sql
   *   Reference to the SQL object.
   * @param array $params
   *   The list of params as provided by the action.
   */
  public static function filter(CRM_Utils_SQL_Select $sql, array &$params) {
    foreach ($params as $key => $value) {
      if (!empty(preg_match('/^custom_/i', $key))) {
        $id = substr($key, 7);
        $field = CRM_Core_BAO_CustomField::getFieldObject($id);
        $isMultiple = CRM_Core_BAO_CustomField::hasOptions($field);

        if (!$isMultiple) {
          continue;
        }

        $columnGroup = CRM_Core_BAO_CustomField::getTableColumnGroup($id);

        if (!empty($columnGroup)) {
          // Remove this field,so the case API won't use it in filtering.
          unset($params[$key]);

          self::joinCustomFieldQuery($sql, $columnGroup, (array) $value);
        }
      }
    }
  }

  /**
   * Joins the custom fields table column with filter condition.
   *
   * @param CRM_Utils_SQL_Select $sql
   *   Reference to the SQL object.
   * @param array $columnGroup
   *   Array containing details on the custom field to join.
   * @param array $value
   *   Array containing the custom field values to filter.
   */
  private static function joinCustomFieldQuery(CRM_Utils_SQL_Select $sql, array $columnGroup, array $value) {
    // When the param is keyed by 'IN', transform back to normal.
    $value = $value['IN'] ?? $value;
    $table = $columnGroup[0];
    $column = $columnGroup[1];
    $mysqlVersion = CRM_Core_DAO::executeQuery('SELECT VERSION() AS version;')->fetchValue();
    $isMysql8 = version_compare($mysqlVersion, '8.0') !== -1;

    $conditions = array_map(function ($param) use ($table, $column, $isMysql8) {
      return $isMysql8 ? "custom_case_to_{$table}.{$column} REGEXP '\\b" . $param . "\\b'" : "custom_case_to_{$table}.{$column} REGEXP '[[:<:]]" . $param . "[[:>:]]'";
    }, $value);
    $conditions = implode(" AND ", $conditions);

    $sql->join(
      "custom_case_to_{$table}", "LEFT JOIN {$table} AS custom_case_to_{$table}
      ON custom_case_to_{$table}.entity_id = `a`.id"
    );
    $sql->where($conditions);
  }

}
