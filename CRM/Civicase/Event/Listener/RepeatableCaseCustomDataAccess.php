<?php

use Civi\API\Event\AuthorizeEvent;
use Civi\Api4\Utils\CoreUtil;
use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Access rules for records of repeatable (multi-record) Case custom groups.
 *
 * Adding, editing or deleting a record of a group requires edit access to
 * that group: the "access all custom data" permission, or a CiviCRM ACL that
 * grants "Edit" on the group (core's CRM_Core_BAO_CustomGroup::checkGroupAccess
 * covers both). Viewing is left to core's rules. See TCOSB-51.
 *
 * Enforced through CiviCRM's supported extension points only, so callers that
 * opt out of permission checks (`checkPermissions => FALSE` in APIv4,
 * `check_permissions` unset/FALSE in APIv3) are respected:
 *  - authorizeApi(): APIv3 and APIv4 (`civi.api.authorize`). For APIv4 this
 *    also drives `checkAccess` and SearchKit's permitted actions, so the case
 *    tab hides Add/Edit, inline edits and the bulk tasks;
 *  - CRM_Civicase_Hook_ValidateForm_RestrictRepeatableCaseCustomData:
 *    civicrm/case/cd/edit (CRM_Case_Form_CustomData), the only QuickForm that
 *    saves repeatable Case custom data, without going through the API;
 *  - deleteCustomValueAjax(): the classic `civicrm/ajax/customvalue` delete
 *    (wired via hook_alterMenu), which core runs without any API/form hook.
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAccess {

  /**
   * Permission that grants edit access to every custom group.
   *
   * Without it, an ACL granting "Edit" on a group gives access to that group.
   */
  const MANAGE_PERMISSION = 'access all custom data';

  /**
   * APIv4 actions that write records and so need edit access.
   */
  const API4_WRITE_ACTIONS = ['create', 'update', 'delete', 'save', 'replace'];

  /**
   * Whether a user may add, edit or delete records of a custom group.
   *
   * @param array $group
   *   CustomGroup record (needs `id`).
   * @param int|null $userId
   *   Contact ID to check, or NULL for the logged-in user.
   *
   * @return bool
   *   TRUE with "access all custom data" or an ACL granting "Edit" on the
   *   group.
   */
  public static function canManageGroup(array $group, ?int $userId = NULL): bool {
    if (\CRM_Core_Permission::check(self::MANAGE_PERMISSION, $userId)) {
      return TRUE;
    }

    return \CRM_Core_BAO_CustomGroup::checkGroupAccess(
      (int) ($group['id'] ?? 0),
      \CRM_Core_Permission::EDIT,
      $userId
    );
  }

  /**
   * Listener for `civi.api.authorize`: gates APIv3 and APIv4 writes.
   *
   * Only applies when the call checks permissions. Runs at default priority,
   * before core's PermissionCheck (W_LATE, which stops propagation); it only
   * ever denies, never grants.
   *
   * @param \Civi\API\Event\AuthorizeEvent $event
   *   The API authorize event.
   */
  public static function authorizeApi(AuthorizeEvent $event): void {
    $apiRequest = $event->getApiRequest();
    $version = (int) ($apiRequest['version'] ?? 0);
    $groups = [];

    if ($version === 4) {
      if ($apiRequest->getCheckPermissions()
        && in_array($apiRequest->getActionName(), self::API4_WRITE_ACTIONS, TRUE)) {
        $group = self::getGatedGroupForEntity((string) $apiRequest->getEntityName());
        $groups = $group ? [$group] : [];
      }
    }
    elseif ($version === 3) {
      $params = $apiRequest['params'] ?? [];
      if (!empty($params['check_permissions'])
        && stripos((string) $apiRequest['action'], 'get') !== 0) {
        $groups = self::getGatedCustomParamGroups($params);
      }
    }

    foreach ($groups as $group) {
      if (!self::canManageGroup($group, $event->getUserID())) {
        $event->setAuthorized(FALSE);
        $event->stopPropagation();
        return;
      }
    }
  }

  /**
   * Page callback wrapping core's `civicrm/ajax/customvalue` delete.
   *
   * Core's CRM_Contact_Page_AJAX::deleteCustomValue deletes straight from the
   * table and only fires hook_civicrm_custom afterwards, so the check has to
   * happen before handing over to it.
   */
  public static function deleteCustomValueAjax(): void {
    $groupId = (int) CRM_Utils_Request::retrieve('groupID', 'Positive');
    $group = $groupId ? \CRM_Core_BAO_CustomGroup::getGroup(['id' => $groupId]) : NULL;
    if (self::isGatedGroup($group) && !self::canManageGroup($group)) {
      CRM_Utils_System::permissionDenied();
      CRM_Utils_System::civiExit();
    }
    CRM_Contact_Page_AJAX::deleteCustomValue();
  }

  /**
   * The keys of submitted params the user may not write.
   *
   * @param array $params
   *   Submitted form values or API params.
   * @param int|null $userId
   *   Contact ID to check, or NULL for the logged-in user.
   *
   * @return string[]
   *   Keys targeting a gated group the user cannot edit, in submitted order.
   */
  public static function getUnmanageableCustomParamKeys(array $params, ?int $userId = NULL): array {
    $keys = [];
    $canManage = [];
    foreach (self::getGatedCustomParamGroups($params) as $key => $group) {
      $canManage[$group['id']] ??= self::canManageGroup($group, $userId);
      if (!$canManage[$group['id']]) {
        $keys[] = $key;
      }
    }

    return $keys;
  }

  /**
   * The gated groups targeted by submitted params, keyed by param key.
   *
   * Recognises the custom field key formats used by forms and APIv3:
   * `custom_<fieldId>`, `custom_<fieldId>_<recordId|-n>` and
   * `custom_<groupName>:<fieldName>[:<recordId>]`.
   *
   * @param array $params
   *   Submitted form values or API params.
   *
   * @return array[]
   *   CustomGroup records keyed by the param key that targets them, in
   *   submitted order; only gated groups are included.
   */
  public static function getGatedCustomParamGroups(array $params): array {
    $groups = [];
    // Several keys usually belong to the same group; decide each group once.
    $gatedByGroup = [];
    foreach (array_keys($params) as $key) {
      $key = (string) $key;
      if (preg_match('/^custom_(\d+)(?:_-?\d+)?$/', $key, $match)) {
        $field = \CRM_Core_BAO_CustomField::getField((int) $match[1]);
        $group = $field['custom_group'] ?? NULL;
      }
      elseif (preg_match('/^custom_([^:]+):/', $key, $match)) {
        $group = \CRM_Core_BAO_CustomGroup::getGroup(['name' => $match[1]]);
      }
      else {
        continue;
      }
      if (empty($group['id'])) {
        continue;
      }
      $gatedByGroup[$group['id']] ??= self::isGatedGroup($group);
      if ($gatedByGroup[$group['id']]) {
        $groups[$key] = $group;
      }
    }

    return $groups;
  }

  /**
   * The gated custom group behind an APIv4 entity (Custom_<name>), if any.
   *
   * @param string $entityName
   *   APIv4 entity name.
   *
   * @return array|null
   *   The CustomGroup record for a multi-record Case custom group, or NULL.
   */
  public static function getGatedGroupForEntity(string $entityName): ?array {
    $groupName = CoreUtil::getCustomGroupName($entityName);
    if (!$groupName) {
      return NULL;
    }
    $group = \CRM_Core_BAO_CustomGroup::getGroup(['name' => $groupName]);

    return self::isGatedGroup($group) ? $group : NULL;
  }

  /**
   * Whether a custom group is gated: multi-record and extending Case.
   *
   * Decided from the group's own metadata (core's cached custom group list),
   * not from how it is displayed, so it also covers groups that are disabled
   * or not shown as a "Tab with table".
   *
   * @param array|null $group
   *   CustomGroup record, or NULL if not found.
   *
   * @return bool
   *   TRUE if writes to the group need edit access to it.
   */
  public static function isGatedGroup(?array $group): bool {
    if (empty($group) || empty($group['is_multiple'])) {
      return FALSE;
    }

    return in_array($group['extends'] ?? NULL, CaseCustomAfforms::getCaseExtends(), TRUE);
  }

}
