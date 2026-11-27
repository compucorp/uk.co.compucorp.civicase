<?php

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\CustomField;
use Civi\Api4\Utils\CoreUtil;

/**
 * Imports one CSV row of repeatable Case custom data.
 *
 * Used by the CaseCustomImporter.create API, which nz.co.fuzion.csvimport calls
 * once per CSV row. The parent Case is matched by Case ID. Every repeatable
 * ("Tab with table") Case custom group has its own "Custom Value ID" column,
 * so a row creates or updates at most one record per group: for each group,
 * a blank ID creates a new record from the group's values on the row and a
 * populated ID updates that record in place.
 */
class CRM_Civicase_Service_RepeatableCaseCustomImporter {

  /**
   * What CiviCRM's import parser passes for a cell it could not convert.
   *
   * For example an option field value that is none of the field's options.
   */
  const INVALID_VALUE = 'invalid_import_value';

  /**
   * Custom field data types a CSV cell cannot supply (a file upload).
   */
  const UNSUPPORTED_DATA_TYPES = ['File'];

  /**
   * Prefix of the per-group record ID column, followed by the group ID.
   */
  const RECORD_ID_KEY_PREFIX = 'custom_value_id_';

  /**
   * Importable fields of all repeatable Case custom groups, keyed by group id.
   *
   * Active fields of every data type a CSV cell can supply. Fetched in a
   * single query and cached for the request in Civi::$statics (reset along
   * with the container), so importing many rows does not re-query per row or
   * per group.
   *
   * @return array
   *   [ groupId => [ ['id' => .., 'name' => .., 'label' => .., ...], ... ] ].
   */
  private static function fieldsByGroup(): array {
    if (isset(\Civi::$statics[__CLASS__]['fieldsByGroup'])) {
      return \Civi::$statics[__CLASS__]['fieldsByGroup'];
    }
    $service = new CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms();
    $groupIds = array_column($service->getRepeatableCaseGroups(), 'id');
    $byGroup = [];
    if ($groupIds) {
      $fields = CustomField::get(FALSE)
        ->addSelect('id', 'name', 'label', 'custom_group_id', 'option_group_id', 'data_type', 'serialize')
        ->addWhere('custom_group_id', 'IN', $groupIds)
        ->addWhere('is_active', '=', TRUE)
        ->addWhere('data_type', 'NOT IN', self::UNSUPPORTED_DATA_TYPES)
        ->execute();
      foreach ($fields as $field) {
        $byGroup[$field['custom_group_id']][] = $field;
      }
    }
    \Civi::$statics[__CLASS__]['fieldsByGroup'] = $byGroup;
    return $byGroup;
  }

  /**
   * The mapping key of a group's record ID column.
   *
   * @param int $groupId
   *   Custom group ID.
   *
   * @return string
   *   For example `custom_value_id_12`.
   */
  public static function recordIdKey(int $groupId): string {
    return self::RECORD_ID_KEY_PREFIX . $groupId;
  }

  /**
   * The label of a group's record ID column.
   *
   * The same label a SearchKit export gives the group's record ID column,
   * so an exported file maps straight back.
   *
   * @param array $group
   *   CustomGroup record (needs `title`).
   *
   * @return string
   *   For example "Education history: Custom Value ID".
   */
  public static function recordIdLabel(array $group): string {
    return $group['title'] . ': ' . ts('Custom Value ID');
  }

  /**
   * The record ID columns, one per repeatable Case custom group.
   *
   * @return array
   *   [ 'custom_value_id_<groupId>' => '<Group title>: Custom Value ID' ].
   */
  public static function recordIdFields(): array {
    $service = new CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms();
    $out = [];
    foreach ($service->getRepeatableCaseGroups() as $group) {
      $out[self::recordIdKey((int) $group['id'])] = self::recordIdLabel($group);
    }
    return $out;
  }

  /**
   * Columns a CSV can map, keyed by `custom_<fieldId>` => human label.
   *
   * One entry per importable field of every repeatable Case custom group, so
   * the importer's field list mirrors what is configurable. File fields
   * are left out, as a CSV cell cannot upload a file.
   *
   * @return array
   *   [ 'custom_<id>' => '<Group title>: <Field label>', ... ]
   */
  public static function mappableFields(): array {
    $service = new CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms();
    $byGroup = self::fieldsByGroup();
    $out = [];
    foreach ($service->getRepeatableCaseGroups() as $group) {
      foreach ($byGroup[$group['id']] ?? [] as $field) {
        $label = $group['title'] . ': ' . $field['label'];
        $out['custom_' . $field['id']] = $label;
      }
    }
    return $out;
  }

  /**
   * Import metadata that makes CiviCRM's import parser convert a column.
   *
   * The parser converts an option label (or name) in the CSV to the stored
   * option value only when a column's metadata identifies the multi-record
   * custom field it belongs to; without it the label text is stored verbatim,
   * which no option matches. Country and StateProvince fields have no option
   * group but store an ID, so a name in the CSV is rejected unless it is
   * converted the same way. A multi-select field is also marked as serialized,
   * so a comma-separated cell is split into its options.
   *
   * Money fields are given the Money data type, so an amount with a currency
   * symbol or thousands separators (as a SearchKit export writes it) is
   * cleaned to a plain number. Only the parser reads `data_type`; the API
   * still takes the column as text, so an amount the parser rejects reaches
   * the importer, which reports it.
   *
   * @return array
   *   [ 'custom_<id>' => ['custom_field_id' => .., 'is_multiple' => 1,
   *     'custom_group_id.name' => .., 'serialize' => ..], ... ] for option,
   *   Country and StateProvince fields, and
   *   [ 'custom_<id>' => ['data_type' => 'Money'] ] for Money fields.
   */
  public static function parserFieldMetadata(): array {
    $service = new CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms();
    $byGroup = self::fieldsByGroup();
    $out = [];
    foreach ($service->getRepeatableCaseGroups() as $group) {
      foreach ($byGroup[$group['id']] ?? [] as $field) {
        $key = 'custom_' . $field['id'];
        if ($field['data_type'] === 'Money') {
          $out[$key] = ['data_type' => 'Money'];
        }
        elseif (!empty($field['option_group_id']) || in_array($field['data_type'], ['Country', 'StateProvince'], TRUE)) {
          $out[$key] = [
            'custom_field_id' => $field['id'],
            'is_multiple' => 1,
            'custom_group_id.name' => $group['name'],
          ];
          if (!empty($field['serialize'])) {
            $out[$key]['serialize'] = $field['serialize'];
          }
        }
      }
    }
    return $out;
  }

  /**
   * Creates or updates repeatable custom record(s) for one import row.
   *
   * For each group, a blank or unmapped `custom_value_id_<groupId>` creates a
   * new record from the group's non-blank values on the row; a populated one
   * updates that record, which must be in the group and on the row's Case.
   * On update, a mapped blank cell clears the stored value and an unmapped
   * column is left unchanged. The whole row is checked before anything is
   * written, and is written in one transaction, so a rejected row changes
   * nothing.
   *
   * @param array $params
   *   Row values: `case_id`, any `custom_<fieldId>` columns, any
   *   `custom_value_id_<groupId>` columns (match keys only, never stored as
   *   values) and, from the API, `check_permissions`.
   *
   * @return int
   *   Number of records created or updated.
   *
   * @throws CRM_Core_Exception
   *   When the row is rejected; the message says why.
   */
  public static function importRow(array $params): int {
    $caseId = (int) ($params['case_id'] ?? 0);
    if (!$caseId) {
      throw new CRM_Core_Exception(ts('case_id is required'));
    }
    $caseExists = civicrm_api4('Case', 'get', [
      'select' => ['id'],
      'where' => [['id', '=', $caseId]],
      'checkPermissions' => FALSE,
    ])->count();
    if (!$caseExists) {
      throw new CRM_Core_Exception(ts('Case %1 not found', [1 => $caseId]));
    }
    if (!empty($params['check_permissions'])) {
      self::assertCanUpdateCase($caseId);
    }

    $service = new CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms();
    $byGroup = self::fieldsByGroup();
    $writes = [];
    $invalid = [];
    foreach ($service->getRepeatableCaseGroups() as $group) {
      $recordId = self::getRecordId($params, $group);
      $fields = $byGroup[$group['id']] ?? [];
      [$values, $isMapped, $invalidLabels] = self::getGroupValues($params, $group, $fields, $recordId !== NULL);
      $invalid = array_merge($invalid, $invalidLabels);

      if ($recordId !== NULL) {
        if (!$isMapped) {
          throw new CRM_Core_Exception(ts('%1 is %2, but no %3 fields are mapped to update', [
            1 => self::recordIdLabel($group),
            2 => $recordId,
            3 => $group['title'],
          ]));
        }
        self::assertRecordIsOnCase($group, $recordId, $caseId);
        $writes[] = ['group' => $group, 'id' => $recordId, 'values' => $values];
      }
      elseif ($values) {
        $writes[] = ['group' => $group, 'id' => NULL, 'values' => $values];
      }
    }

    if ($invalid) {
      throw new CRM_Core_Exception(ts('Invalid value for field(s): %1', [1 => implode(', ', $invalid)]));
    }
    if (!$writes) {
      throw new CRM_Core_Exception(
        ts('Row has no repeatable Case custom field values to import')
      );
    }

    $transaction = new CRM_Core_Transaction();
    try {
      foreach ($writes as $write) {
        self::writeRecord($write['group'], $caseId, $write['id'], $write['values']);
      }
    }
    catch (Throwable $e) {
      $transaction->rollback();
      throw $e;
    }
    $transaction->commit();

    return count($writes);
  }

  /**
   * The record ID a row gives for a group, if any.
   *
   * @param array $params
   *   Row values.
   * @param array $group
   *   CustomGroup record.
   *
   * @return int|null
   *   The ID, or NULL when the column is blank or not mapped.
   *
   * @throws CRM_Core_Exception
   *   When the ID is not a positive whole number.
   */
  private static function getRecordId(array $params, array $group): ?int {
    $raw = trim((string) ($params[self::recordIdKey((int) $group['id'])] ?? ''));
    if ($raw === '') {
      return NULL;
    }
    if (!ctype_digit($raw) || (int) $raw === 0) {
      throw new CRM_Core_Exception(ts('%1 must be a positive whole number, not "%2"', [
        1 => self::recordIdLabel($group),
        2 => $raw,
      ]));
    }
    return (int) $raw;
  }

  /**
   * The values a row supplies for one group's fields.
   *
   * @param array $params
   *   Row values.
   * @param array $group
   *   CustomGroup record.
   * @param array $fields
   *   The group's importable fields.
   * @param bool $isUpdate
   *   Whether the row updates an existing record of the group.
   *
   * @return array
   *   [ values keyed by field name, whether any of the group's fields are
   *   mapped, labels of the fields with a value the parser rejected ]. A
   *   mapped blank cell is a NULL value (clearing it) on update, and left out
   *   on create.
   */
  private static function getGroupValues(array $params, array $group, array $fields, bool $isUpdate): array {
    $values = [];
    $isMapped = FALSE;
    $invalid = [];
    foreach ($fields as $field) {
      $key = 'custom_' . $field['id'];
      if (!array_key_exists($key, $params)) {
        continue;
      }
      $isMapped = TRUE;
      $value = $params[$key];
      if (self::isBlank($value)) {
        if ($isUpdate) {
          $values[$field['name']] = NULL;
        }
        continue;
      }
      if ($value === self::INVALID_VALUE || (is_array($value) && in_array(self::INVALID_VALUE, $value, TRUE))) {
        $invalid[] = $group['title'] . ': ' . $field['label'];
        continue;
      }
      if (!empty($field['serialize']) && !is_array($value)) {
        $value = [$value];
      }
      $values[$field['name']] = $value;
    }
    return [$values, $isMapped, $invalid];
  }

  /**
   * Whether a cell is blank.
   *
   * @param mixed $value
   *   Cell value.
   *
   * @return bool
   *   TRUE for NULL, an empty or whitespace-only string, or an empty list.
   *   Other values, such as 0 or FALSE, are values.
   */
  private static function isBlank($value): bool {
    return $value === NULL
      || $value === []
      || (is_string($value) && trim($value) === '');
  }

  /**
   * Rejects a row for a Case the importing user may not update.
   *
   * Needs the Case permission an update requires, and the Case to be one
   * the user can see (so "my cases" and case category permissions apply).
   * Custom data permissions are checked for the API call as a whole, by
   * CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAccess.
   *
   * @param int $caseId
   *   Case ID.
   *
   * @throws CRM_Core_Exception
   */
  private static function assertCanUpdateCase(int $caseId): void {
    $userId = (int) CRM_Core_Session::getLoggedInContactID();
    $canUpdate = CoreUtil::checkAccessDelegated('Case', 'update', ['id' => $caseId], $userId);
    if ($canUpdate) {
      try {
        $canUpdate = (bool) civicrm_api4('Case', 'get', [
          'select' => ['id'],
          'where' => [['id', '=', $caseId]],
          'checkPermissions' => TRUE,
        ])->count();
      }
      catch (UnauthorizedException $e) {
        $canUpdate = FALSE;
      }
    }
    if (!$canUpdate) {
      throw new CRM_Core_Exception(ts('You do not have permission to update Case %1', [1 => $caseId]));
    }
  }

  /**
   * Rejects a record ID that is not a record of the group on the Case.
   *
   * @param array $group
   *   CustomGroup record.
   * @param int $recordId
   *   The ID from the row.
   * @param int $caseId
   *   The row's Case ID.
   *
   * @throws CRM_Core_Exception
   */
  private static function assertRecordIsOnCase(array $group, int $recordId, int $caseId): void {
    $record = civicrm_api4('Custom_' . $group['name'], 'get', [
      'select' => ['entity_id'],
      'where' => [['id', '=', $recordId]],
      'checkPermissions' => FALSE,
    ])->first();
    if (!$record) {
      throw new CRM_Core_Exception(ts('No %1 record has Custom Value ID %2', [
        1 => $group['title'],
        2 => $recordId,
      ]));
    }
    if ((int) $record['entity_id'] !== $caseId) {
      throw new CRM_Core_Exception(ts('%1 record %2 belongs to another Case, not Case %3', [
        1 => $group['title'],
        2 => $recordId,
        3 => $caseId,
      ]));
    }
  }

  /**
   * Creates a record of a group on a Case, or updates one in place.
   *
   * @param array $group
   *   CustomGroup record.
   * @param int $caseId
   *   Case ID.
   * @param int|null $recordId
   *   The record to update, or NULL to create one.
   * @param array $values
   *   Values keyed by field name.
   */
  private static function writeRecord(array $group, int $caseId, ?int $recordId, array $values): void {
    $entity = 'Custom_' . $group['name'];
    if ($recordId === NULL) {
      civicrm_api4($entity, 'create', [
        'values' => ['entity_id' => $caseId] + $values,
        'checkPermissions' => FALSE,
      ]);
      return;
    }
    // Scoped to the Case as well, so the record can never move between Cases.
    civicrm_api4($entity, 'update', [
      'values' => $values,
      'where' => [['id', '=', $recordId], ['entity_id', '=', $caseId]],
      'checkPermissions' => FALSE,
    ]);
  }

}
