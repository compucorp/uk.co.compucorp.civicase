<?php

use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Custom Field Options Filter class.
 */
class CRM_Civicase_APIHelpers_CustomFieldFilter {

  /**
   * Handles filtering of case custom fields with multiple options.
   *
   * Fields of repeatable (multi-record) Case custom groups are handled first,
   * see filterRepeatableGroups(); the remaining fields keep their existing
   * handling.
   *
   * @param CRM_Utils_SQL_Select $sql
   *   Reference to the SQL object.
   * @param array $params
   *   The list of params as provided by the action.
   */
  public static function filter(CRM_Utils_SQL_Select $sql, array &$params) {
    self::filterRepeatableGroups($sql, $params);

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
   * Filters by fields of repeatable (multi-record) Case custom groups.
   *
   * @param CRM_Utils_SQL_Select $sql
   *   Reference to the SQL object.
   * @param array $params
   *   The list of params as provided by the action.
   */
  private static function filterRepeatableGroups(CRM_Utils_SQL_Select $sql, array &$params) {
    $conditionsByGroup = [];
    $tablesByGroup = [];
    $onlyIsNullByGroup = [];
    foreach ($params as $key => $value) {
      if (!preg_match('/^custom_(\d+)$/', (string) $key, $match)) {
        continue;
      }
      $field = CRM_Core_BAO_CustomField::getField((int) $match[1]);
      if (empty($field) || !self::isRepeatableCaseGroup($field['custom_group'] ?? [])) {
        continue;
      }

      unset($params[$key]);
      if (self::isEmptyFilterValue($value)) {
        continue;
      }

      $groupId = (int) $field['custom_group']['id'];
      $alias = 'repeatable_case_group_' . $groupId;
      $tablesByGroup[$groupId] = $field['custom_group']['table_name'];
      $conditionsByGroup[$groupId][] = self::getRepeatableFieldCondition(
        "`{$alias}`.`{$field['column_name']}`",
        (int) $field['id'],
        $value
      );
      $isNull = is_array($value) && array_keys($value) === ['IS NULL'];
      $onlyIsNullByGroup[$groupId] = ($onlyIsNullByGroup[$groupId] ?? TRUE) && $isNull;
    }

    foreach ($conditionsByGroup as $groupId => $conditions) {
      $alias = 'repeatable_case_group_' . $groupId;
      $table = $tablesByGroup[$groupId];
      $condition = "EXISTS (
        SELECT 1 FROM `{$table}` AS `{$alias}`
        WHERE `{$alias}`.entity_id = a.id
        AND " . implode(' AND ', $conditions) . '
      )';
      if ($onlyIsNullByGroup[$groupId]) {
        $condition = "({$condition} OR NOT EXISTS (
          SELECT 1 FROM `{$table}` AS `{$alias}_any`
          WHERE `{$alias}_any`.entity_id = a.id
        ))";
      }
      $sql->where($condition);
    }
  }

  /**
   * Builds the condition for one field of a repeatable Case group.
   *
   * The value is either a single value or a list of values, or an operator
   * array such as `['BETWEEN' => [$from, $to]]` or
   * `['>=' => $from, '<=' => $to]`. Each operator becomes its own condition and
   * all of them must be met, so no operator is dropped.
   *
   * @param string $column
   *   The quoted column, including the table alias.
   * @param int $fieldId
   *   Custom field ID.
   * @param mixed $value
   *   The filter value.
   *
   * @return string
   *   The SQL condition.
   *
   * @throws CRM_Core_Exception
   *   When the value uses an unsupported operator.
   */
  private static function getRepeatableFieldCondition(string $column, int $fieldId, $value): string {
    $field = CRM_Core_BAO_CustomField::getFieldObject($fieldId);
    if (!self::isOperatorFilter($value)) {
      return self::getValuesCondition($column, $field, (array) $value);
    }

    $conditions = [];
    foreach ($value as $operator => $criteria) {
      if (self::isEmptyListOperand($operator, $criteria)) {
        continue;
      }
      if ($operator === 'IN') {
        $conditions[] = self::getValuesCondition($column, $field, (array) $criteria);
        continue;
      }

      $condition = CRM_Core_DAO::createSQLFilter($column, [$operator => $criteria]);
      if (empty($condition)) {
        throw new CRM_Core_Exception(ts('Invalid filter for custom field %1.', [1 => $fieldId]));
      }
      $conditions[] = $condition;
    }

    return empty($conditions) ? '1' : implode(' AND ', $conditions);
  }

  /**
   * Builds the condition matching a value or list of values of a field.
   *
   * @param string $column
   *   The quoted column, including the table alias.
   * @param CRM_Core_BAO_CustomField $field
   *   The custom field.
   * @param array $values
   *   The values to match.
   *
   * @return string
   *   The SQL condition: all the options must be selected on option fields,
   *   any of the values must be present on other multi-value fields (e.g. a
   *   contact reference allowing several contacts), and the value must be one
   *   of the values otherwise.
   */
  private static function getValuesCondition(string $column, CRM_Core_BAO_CustomField $field, array $values): string {
    if (CRM_Core_BAO_CustomField::hasOptions($field)) {
      return self::getOptionsCondition($column, $values);
    }

    if (CRM_Core_BAO_CustomField::isSerialized($field)) {
      return self::getOptionsCondition($column, $values, 'OR');
    }

    return CRM_Core_DAO::createSQLFilter($column, ['IN' => array_values($values)]);
  }

  /**
   * Whether a filter value is an operator array, e.g. `['>=' => 1]`.
   *
   * @param mixed $value
   *   The filter value.
   *
   * @return bool
   *   TRUE when every key of the array is a supported SQL operator.
   */
  private static function isOperatorFilter($value): bool {
    if (!is_array($value) || empty($value)) {
      return FALSE;
    }
    foreach (array_keys($value) as $operator) {
      if (!is_string($operator) || !in_array($operator, CRM_Core_DAO::acceptedSQLOperators(), TRUE)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Whether an operator is IN or NOT IN with an empty list.
   *
   * @param string $operator
   *   The SQL operator.
   * @param mixed $criteria
   *   Its operand.
   *
   * @return bool
   *   TRUE when the operator filters nothing.
   */
  private static function isEmptyListOperand(string $operator, $criteria): bool {
    return in_array($operator, ['IN', 'NOT IN'], TRUE)
      && ($criteria === NULL || $criteria === '' || $criteria === []);
  }

  /**
   * Builds a condition matching records that have the given values.
   *
   * @param string $column
   *   The quoted column, including the table alias.
   * @param array $values
   *   Values to look for.
   * @param string $glue
   *   'AND' when all the values must be present, 'OR' when any of them.
   *
   * @return string
   *   The SQL condition.
   */
  private static function getOptionsCondition(string $column, array $values, string $glue = 'AND'): string {
    $separator = CRM_Core_DAO::VALUE_SEPARATOR;
    $conditions = [];
    foreach ($values as $value) {
      $needle = CRM_Core_DAO::escapeString($separator . $value . $separator);
      $conditions[] = "LOCATE('{$needle}', CONCAT('{$separator}', {$column}, '{$separator}')) > 0";
    }

    return empty($conditions) ? '1' : '(' . implode(" {$glue} ", $conditions) . ')';
  }

  /**
   * Whether a custom group is a repeatable (multi-record) Case group.
   *
   * @param array $group
   *   Custom group record.
   *
   * @return bool
   *   TRUE when the group allows multiple records and extends Cases.
   */
  private static function isRepeatableCaseGroup(array $group): bool {
    return !empty($group['is_multiple'])
      && in_array($group['extends'] ?? NULL, CaseCustomAfforms::getCaseExtends(), TRUE);
  }

  /**
   * Whether a filter value is empty, i.e. not filtering anything.
   *
   * @param mixed $value
   *   The filter value.
   *
   * @return bool
   *   TRUE for NULL, an empty string, an empty list, or only IN / NOT IN with
   *   an empty list (e.g. `['IN' => []]`); `0` is a value.
   */
  private static function isEmptyFilterValue($value): bool {
    if ($value === NULL || $value === '' || $value === []) {
      return TRUE;
    }
    if (!self::isOperatorFilter($value)) {
      return FALSE;
    }
    foreach ($value as $operator => $criteria) {
      if (!self::isEmptyListOperand($operator, $criteria)) {
        return FALSE;
      }
    }

    return TRUE;
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
      return $isMysql8 ? "custom_case_to_{$table}.{$column} REGEXP '\\\b" . $param . "\\\b'" : "custom_case_to_{$table}.{$column} REGEXP '[[:<:]]" . $param . "[[:>:]]'";
    }, $value);
    $conditions = implode(" AND ", $conditions);

    $sql->join(
      "custom_case_to_{$table}", "LEFT JOIN {$table} AS custom_case_to_{$table}
      ON custom_case_to_{$table}.entity_id = `a`.id"
    );
    $sql->where($conditions);
  }

}
