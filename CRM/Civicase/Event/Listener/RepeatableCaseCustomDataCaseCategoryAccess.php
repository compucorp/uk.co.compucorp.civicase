<?php

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\Event\AuthorizeRecordEvent;
use CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAccess as RecordAccess;
use CRM_Civicase_Helper_CaseCategory as CaseCategoryHelper;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;

/**
 * Lets case category users write the records of repeatable Case groups.
 *
 * The Case itself stays read only to these users through APIv4.
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataCaseCategoryAccess {

  /**
   * Case permissions that let a user work on the Cases of a category.
   */
  const CASE_PERMISSIONS = [
    'access my cases and activities',
    'access all cases and activities',
  ];

  /**
   * Listener for `civi.api4.authorizeRecord`: writes by case category users.
   *
   * @param \Civi\Api4\Event\AuthorizeRecordEvent $event
   *   The record authorization event.
   */
  public static function authorizeRecord(AuthorizeRecordEvent $event): void {
    if ($event->isAuthorized() !== FALSE || $event->getActionName() === 'get') {
      return;
    }
    $group = RecordAccess::getGatedGroupForEntity($event->getEntityName());
    $userId = (int) $event->getUserID();
    if (!$group || !$userId || !RecordAccess::canManageGroup($group, $userId)) {
      return;
    }

    $caseId = self::getRecordCaseId($group, $event->getRecord());
    if ($caseId && self::canWorkOnCase($caseId, $userId)) {
      $event->setAuthorized(TRUE);
    }
  }

  /**
   * The Case a record is written to.
   *
   * @param array $group
   *   The record's group, with its name.
   * @param array $record
   *   The record as written: a new record's Case (`entity_id` or `entityID`),
   *   or an existing record's `id`.
   *
   * @return int|null
   *   The Case, or NULL for an unknown record, one moved to another Case, or
   *   one that names two different Cases.
   */
  private static function getRecordCaseId(array $group, array $record): ?int {
    $givenCaseIds = [];
    foreach (RecordAccess::getGivenCaseIds($record) as $givenCaseId) {
      if (!CRM_Utils_Rule::positiveInteger($givenCaseId)) {
        return NULL;
      }
      $givenCaseIds[(int) $givenCaseId] = (int) $givenCaseId;
    }
    if (count($givenCaseIds) > 1) {
      return NULL;
    }
    $givenCaseId = $givenCaseIds ? reset($givenCaseIds) : NULL;
    if (empty($record['id'])) {
      return $givenCaseId;
    }

    $storedCaseId = self::getStoredCaseId($group, (int) $record['id']);
    if (!$storedCaseId || ($givenCaseId !== NULL && $givenCaseId !== $storedCaseId)) {
      return NULL;
    }

    return $storedCaseId;
  }

  /**
   * The Case an existing record is stored on.
   *
   * Cached for the request, as a records table checks each row's links.
   *
   * @param array $group
   *   The record's group, with its name.
   * @param int $recordId
   *   The record.
   *
   * @return int|null
   *   The Case, or NULL for an unknown record.
   */
  private static function getStoredCaseId(array $group, int $recordId): ?int {
    $cached = Civi::$statics[__CLASS__]['storedCaseIds'][$group['name']][$recordId] ?? NULL;
    if ($cached !== NULL) {
      return $cached;
    }

    // The stored record is read whatever the user's access, to compare Cases.
    $storedRecord = civicrm_api4('Custom_' . $group['name'], 'get', [
      'checkPermissions' => FALSE,
      'select' => ['entity_id'],
      'where' => [['id', '=', $recordId]],
    ])->first();
    $storedCaseId = is_array($storedRecord) ? (int) $storedRecord['entity_id'] : 0;
    if (!$storedCaseId) {
      return NULL;
    }
    Civi::$statics[__CLASS__]['storedCaseIds'][$group['name']][$recordId] = $storedCaseId;

    return $storedCaseId;
  }

  /**
   * Whether the logged-in user works on a Case through its category.
   *
   * Cached per user and Case, as a records table checks each row's links.
   *
   * @param int $caseId
   *   The Case.
   * @param int $userId
   *   Contact id of the user.
   *
   * @return bool
   *   TRUE when the user has a case permission for the Case's category and
   *   can see the Case.
   */
  private static function canWorkOnCase(int $caseId, int $userId): bool {
    // Seeing the Case is checked as the logged-in user.
    if ($userId !== (int) CRM_Core_Session::getLoggedInContactID()) {
      return FALSE;
    }
    if (isset(Civi::$statics[__CLASS__]['canWorkOnCase'][$userId][$caseId])) {
      return Civi::$statics[__CLASS__]['canWorkOnCase'][$userId][$caseId];
    }

    $category = CaseCategoryHelper::getCategoryName($caseId);
    $canWork = $category
      && self::hasCasePermission($category, $userId)
      && self::canSeeCase($caseId);
    Civi::$statics[__CLASS__]['canWorkOnCase'][$userId][$caseId] = $canWork;

    return $canWork;
  }

  /**
   * Whether the user has "access my/all cases" for a case category.
   *
   * @param string $category
   *   Case category name.
   * @param int $userId
   *   Contact id of the user.
   *
   * @return bool
   *   TRUE with "access my/all <category> and activities".
   */
  private static function hasCasePermission(string $category, int $userId): bool {
    $categoryPermission = new CaseCategoryPermission();
    foreach (self::CASE_PERMISSIONS as $permission) {
      if (CRM_Core_Permission::check($categoryPermission->replaceWords($permission, $category), $userId)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Whether the logged-in user can see a Case.
   *
   * @param int $caseId
   *   The Case.
   *
   * @return bool
   *   TRUE when the Case is found with the user's permissions.
   */
  private static function canSeeCase(int $caseId): bool {
    try {
      // APIv4 checks permissions by default; set explicitly as it is the point.
      return civicrm_api4('Case', 'get', [
        'checkPermissions' => TRUE,
        'select' => ['id'],
        'where' => [['id', '=', $caseId]],
      ])->count() === 1;
    }
    catch (UnauthorizedException $e) {
      return FALSE;
    }
  }

}
