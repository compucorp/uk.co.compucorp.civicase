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
   * Creates or updates repeatable custom record(s) for one import row.
   *
   * When `id` is supplied it is used only to match an existing record on the
   * Case, which is then updated; otherwise a new record is created.
   *
   * @param array $params
   *   Row values: `case_id`, any `custom_<fieldId>` columns, and an optional
   *   `id` (the record to update — match key only, never stored as a value).
   *
   * @return int
   *   Number of records created or updated.
   *
   * @throws CRM_Core_Exception
   *   When the Case is missing, an `id` matches no record on the Case, or no
   *   mappable field value is present.
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

    // `id`, when supplied, is a MATCH KEY only: it identifies the existing
    // repeatable record to update. It is never written as a field value.
    // Blank/absent => create a new record.
    $recordId = !empty($params['id']) ? (int) $params['id'] : NULL;

    $service = new CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms();
    $byGroup = self::fieldsByGroup();
    $written = 0;
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

      $entity = 'Custom_' . $group['name'];
      if ($recordId !== NULL) {
        // Update: the matched record must already exist on this Case (and in
        // this group). id is match-only, so it is not part of $values.
        $matched = civicrm_api4($entity, 'get', [
          'select' => ['id'],
          'where' => [['id', '=', $recordId], ['entity_id', '=', $caseId]],
          'checkPermissions' => FALSE,
        ])->count();
        if (!$matched) {
          continue;
        }
        civicrm_api4($entity, 'update', [
          'values' => $values,
          'where' => [['id', '=', $recordId]],
          'checkPermissions' => FALSE,
        ]);
        $written++;
      }
      else {
        // Create: a new record against the Case.
        civicrm_api4($entity, 'create', [
          'values' => $values,
          'checkPermissions' => FALSE,
        ]);
        $written++;
      }
    }

    if ($recordId !== NULL && $written === 0) {
      throw new CRM_Core_Exception(
        ts('No repeatable record %1 found on Case %2', [1 => $recordId, 2 => $caseId])
      );
    }
    if (!$written) {
      throw new CRM_Core_Exception(
        ts('Row has no repeatable Case custom field values to import')
      );
    }
    return $written;
  }

}
