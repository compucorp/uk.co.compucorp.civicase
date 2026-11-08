<?php

use Civi\Api4\Event\ValidateValuesEvent;
use Civi\Api4\Utils\CoreUtil;
use CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAccess as CaseCustomDataAccess;
use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Keeps repeatable Case custom records on the case types of their group.
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataCaseType {

  /**
   * Listener for `civi.api4.validate`.
   *
   * @param \Civi\Api4\Event\ValidateValuesEvent $event
   *   The APIv4 validate event.
   */
  public static function validateValues(ValidateValuesEvent $event): void {
    $groupName = CoreUtil::getCustomGroupName($event->getEntityName());
    if (!$groupName) {
      return;
    }
    $group = \CRM_Core_BAO_CustomGroup::getGroup(['name' => $groupName]);
    if (!CaseCustomDataAccess::isGatedGroup($group)) {
      return;
    }

    foreach (self::getRecordsOnOtherCaseTypes($group, $event->records) as $recordKey) {
      $event->addError(
        $recordKey,
        'entity_id',
        'case_type_not_allowed',
        ts('%1 records cannot be added to cases of this type.', [1 => $group['title']])
      );
    }
  }

  /**
   * The records that would put a group's value on a case it doesn't apply to.
   *
   * @param array $group
   *   CustomGroup record (needs `extends_entity_column_value`).
   * @param array $records
   *   The records being saved, keyed as in the API call.
   *
   * @return array
   *   Keys of the records whose case is of a type the group isn't for.
   *   Records without a case, or with a case that doesn't exist, are left to
   *   core.
   */
  public static function getRecordsOnOtherCaseTypes(array $group, array $records): array {
    $caseTypeIds = CaseCustomAfforms::getGroupCaseTypeIds($group);
    if ($caseTypeIds === NULL) {
      return [];
    }

    $caseIds = [];
    foreach ($records as $key => $record) {
      $caseId = $record['entity_id'] ?? NULL;
      if (is_numeric($caseId) && (int) $caseId > 0) {
        $caseIds[$key] = (int) $caseId;
      }
    }
    if (!$caseIds) {
      return [];
    }

    $caseTypes = self::getCaseTypeIdsByCase(array_unique($caseIds));
    $keys = [];
    foreach ($caseIds as $key => $caseId) {
      if (isset($caseTypes[$caseId]) && !in_array($caseTypes[$caseId], $caseTypeIds, TRUE)) {
        $keys[] = $key;
      }
    }

    return $keys;
  }

  /**
   * The case type of each case.
   *
   * @param int[] $caseIds
   *   Case IDs.
   *
   * @return int[]
   *   Case type IDs keyed by case ID; missing cases are left out.
   */
  private static function getCaseTypeIdsByCase(array $caseIds): array {
    $caseTypes = \CRM_Utils_SQL_Select::from('civicrm_case')
      ->select(['id', 'case_type_id'])
      ->where('id IN (#ids)', ['ids' => $caseIds])
      ->execute()
      ->fetchMap('id', 'case_type_id');

    return array_map('intval', $caseTypes);
  }

}
