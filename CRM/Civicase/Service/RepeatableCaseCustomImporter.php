<?php

use Civi\Api4\CustomField;

/**
 * Imports one CSV row of repeatable Case custom data (TCOSB-64 / 1.8).
 *
 * Used by the CaseCustomImporter.create API, which nz.co.fuzion.csvimport calls
 * once per CSV row. The parent Case is matched by Case ID; each row creates a
 * new record in every repeatable ("Tab with table") Case custom group whose
 * field(s) are present in the row (create-only — AC2/AC3, never overwrites).
 */
class CRM_Civicase_Service_RepeatableCaseCustomImporter {

  /**
   * Per-request cache of active fields grouped by custom group id.
   *
   * @var array|null
   */
  private static $fieldsByGroup = NULL;

  /**
   * Active fields of all repeatable Case custom groups, keyed by group id.
   *
   * Fetched in a single query and cached per request, so importing many rows
   * does not re-query per row or per group.
   *
   * @return array
   *   [ groupId => [ ['id' => .., 'name' => .., 'label' => ..], ... ], ... ]
   */
  private static function fieldsByGroup(): array {
    if (self::$fieldsByGroup !== NULL) {
      return self::$fieldsByGroup;
    }
    $service = new CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms();
    $groupIds = array_column($service->getRepeatableCaseGroups(), 'id');
    self::$fieldsByGroup = [];
    if ($groupIds) {
      $fields = CustomField::get(FALSE)
        ->addSelect('id', 'name', 'label', 'custom_group_id')
        ->addWhere('custom_group_id', 'IN', $groupIds)
        ->addWhere('is_active', '=', TRUE)
        ->execute();
      foreach ($fields as $field) {
        self::$fieldsByGroup[$field['custom_group_id']][] = $field;
      }
    }
    return self::$fieldsByGroup;
  }

  /**
   * Columns a CSV can map, keyed by `custom_<fieldId>` => human label.
   *
   * One entry per active field of every repeatable Case custom group, so the
   * importer's field list mirrors what is configurable (AC1).
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
   * Creates repeatable custom record(s) for one import row.
   *
   * @param array $params
   *   Row values: `case_id` plus any `custom_<fieldId>` columns.
   *
   * @return int
   *   Number of records created.
   *
   * @throws CRM_Core_Exception
   *   When the Case is missing or no mappable field value is present.
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

    $service = new CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms();
    $byGroup = self::fieldsByGroup();
    $created = 0;
    foreach ($service->getRepeatableCaseGroups() as $group) {
      $values = ['entity_id' => $caseId];
      $hasValue = FALSE;
      foreach ($byGroup[$group['id']] ?? [] as $field) {
        $key = 'custom_' . $field['id'];
        if (isset($params[$key]) && $params[$key] !== '') {
          $values[$field['name']] = $params[$key];
          $hasValue = TRUE;
        }
      }
      if (!$hasValue) {
        continue;
      }
      // Create-only: each row makes a new record against the Case.
      civicrm_api4('Custom_' . $group['name'], 'create', [
        'values' => $values,
        'checkPermissions' => FALSE,
      ]);
      $created++;
    }

    if (!$created) {
      throw new CRM_Core_Exception(
        ts('Row has no repeatable Case custom field values to import')
      );
    }
    return $created;
  }

}
