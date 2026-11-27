<?php

use Civi\API\Event\AuthorizeEvent;
use Civi\Api4\Generic\AbstractAction;
use CRM_Case_BAO_CaseType as CaseType;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;

/**
 * Lets case category users read cases through API4.
 *
 * Core only accepts the generic "Cases" permissions for API4 Case reads, and
 * hook_civicrm_alterAPIPermissions only runs for API3. So users with access to
 * other case categories only can't use SearchKit displays on cases.
 *
 * Only "get" queries on Case and CaseContact are allowed. The rows are then
 * limited by the case category ACL clause, see
 * CRM_Civicase_Hook_SelectWhereClause_RestrictCaseQueryByCaseCategoryPermissions.
 */
class CRM_Civicase_Event_Listener_CaseCategoryApiAuthorization {

  /**
   * API4 entities whose rows are limited by the case ACL clause.
   */
  const ENTITIES = ['Case', 'CaseContact'];

  /**
   * Case permissions that give API4 access to a case category.
   */
  const CASE_PERMISSIONS = [
    'access all cases and activities',
    'access my cases and activities',
  ];

  /**
   * Query parameters a join access check never has.
   */
  const QUERY_PARAMS = [
    'select',
    'where',
    'join',
    'groupBy',
    'having',
    'orderBy',
  ];

  /**
   * Authorizes API4 case reads for users with a case category permission.
   *
   * Runs after core's API4 permission check, so only the requests core
   * refused are checked.
   *
   * @param \Civi\API\Event\AuthorizeEvent $event
   *   API authorization event.
   */
  public static function onAuthorize(AuthorizeEvent $event): void {
    $apiRequest = $event->getApiRequest();
    if ($event->isAuthorized() || !self::isCaseQuery($apiRequest)) {
      return;
    }

    $contactId = (int) CRM_Core_Session::getLoggedInContactID();
    if ($contactId && self::hasCaseCategoryAccess($contactId)) {
      $event->authorize();
      $event->stopPropagation();
    }
  }

  /**
   * Whether the request is a permission checked API4 case query.
   *
   * Core checks join access with a bare "get" request (GetActions through
   * Kernel::runAuthorize), so a request without query parameters is refused.
   *
   * @param array|\Civi\Api4\Generic\AbstractAction $apiRequest
   *   API request.
   *
   * @return bool
   *   TRUE for API4 "get" queries on the case entities.
   */
  private static function isCaseQuery($apiRequest): bool {
    if (!$apiRequest instanceof AbstractAction
      || !$apiRequest->getCheckPermissions()
      || $apiRequest->getActionName() !== 'get'
      || !in_array($apiRequest->getEntityName(), self::ENTITIES, TRUE)) {
      return FALSE;
    }

    $params = $apiRequest->getParams();
    foreach ((array) ($params['join'] ?? []) as $join) {
      if (!self::keepsCaseAcl(array_values((array) $join))) {
        return FALSE;
      }
    }

    foreach (self::QUERY_PARAMS as $param) {
      if (!empty($params[$param])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Whether an explicit join keeps the ACL of a joined case entity.
   *
   * Core skips the joined ACL when an explicit join links to a bare main
   * entity field (Api4SelectQuery.php:343-345 and the test at :546). So an
   * explicit case join must use a bridge linked to a non-bare expression.
   *
   * @param array $join
   *   API4 explicit join, read by position as core does.
   *
   * @return bool
   *   FALSE for a join to Case or CaseContact that could skip the case ACL.
   */
  private static function keepsCaseAcl(array $join): bool {
    $target = is_string($join[0] ?? NULL) ? $join[0] : '';
    [$entity, $alias] = array_pad(explode(' AS ', $target), 2, NULL);
    if (!in_array($entity, self::ENTITIES, TRUE)) {
      return TRUE;
    }

    $bridge = $join[2] ?? NULL;
    if (empty($bridge) || !is_string($bridge) || !CRM_Utils_Rule::alphanumeric($bridge)) {
      return FALSE;
    }

    $aliasPrefix = ($alias ?: strtolower($entity)) . '.';
    $isLinked = FALSE;
    foreach (array_slice($join, 3) as $condition) {
      $condition = array_pad(array_values((array) $condition), 3, NULL);
      [$sideA, $operator, $sideB] = $condition;
      if ($operator !== '=' || !is_string($sideA) || !is_string($sideB)) {
        continue;
      }
      $pairs = [[$sideA, $sideB], [$sideB, $sideA]];
      foreach ($pairs as [$joinSide, $otherSide]) {
        if (strpos($joinSide, $aliasPrefix) !== 0) {
          continue;
        }
        if (preg_match('/^[_a-z0-9]+$/i', $otherSide)) {
          return FALSE;
        }
        $isLinked = TRUE;
      }
    }

    return $isLinked;
  }

  /**
   * Whether the contact has a case permission in any case category.
   *
   * Cached per contact. The contact id is passed so the permission is not
   * rewritten to the category found in the current URL.
   *
   * @param int $contactId
   *   Contact id.
   *
   * @return bool
   *   TRUE when the contact can access at least one case category.
   */
  private static function hasCaseCategoryAccess(int $contactId): bool {
    if (isset(Civi::$statics[__CLASS__][$contactId])) {
      return Civi::$statics[__CLASS__][$contactId];
    }

    $hasAccess = FALSE;
    $caseTypeCategories = CaseType::buildOptions('case_type_category', 'validate');
    $caseCategoryPermission = new CaseCategoryPermission();
    foreach ($caseTypeCategories ?: [] as $categoryName) {
      foreach (self::CASE_PERMISSIONS as $permission) {
        $categoryPermission = $caseCategoryPermission->replaceWords($permission, $categoryName);
        if (CRM_Core_Permission::check($categoryPermission, $contactId)) {
          $hasAccess = TRUE;
          break 2;
        }
      }
    }

    Civi::$statics[__CLASS__][$contactId] = $hasAccess;

    return $hasAccess;
  }

}
