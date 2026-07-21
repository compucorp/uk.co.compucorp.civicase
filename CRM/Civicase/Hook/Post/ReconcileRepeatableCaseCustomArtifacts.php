<?php

use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Keeps the repeatable Case custom-data SearchKit artifacts up to date.
 *
 * The per-group SavedSearch + SearchDisplay are declared via
 * hook_civicrm_managed, which only reconciles on a cache flush. So when an
 * admin creates/edits/deletes a repeatable ("Tab with table") Case custom
 * group — or changes its fields — this hook reconciles them straight away, so
 * the case tab renders its table without the admin having to clear the cache.
 * See TCOSB-51.
 */
class CRM_Civicase_Hook_Post_ReconcileRepeatableCaseCustomArtifacts {

  /**
   * Reconciles the managed artifacts after a relevant custom-structure change.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   * @param mixed $objectId
   *   Object ID.
   * @param object $objectRef
   *   Object reference.
   */
  public function run($op, $objectName, $objectId, &$objectRef) {
    // A CustomGroup write can change the repeatable-group list, so drop its
    // cache FIRST — before shouldRun() reads that list to decide whether to
    // reconcile. Clearing at the end would leave shouldRun() (and the reconcile
    // it gates) working off the pre-change list, so a newly created group would
    // never get its artifacts provisioned.
    if ($objectName === 'CustomGroup'
      && in_array($op, ['create', 'edit', 'delete'], TRUE)) {
      CaseCustomAfforms::clearRepeatableCaseGroupsCache();
    }

    if (!$this->shouldRun($op, $objectName, $objectRef)) {
      return;
    }

    CaseCustomAfforms::reconcileManaged();
  }

  /**
   * Whether the change concerns a repeatable Case custom group.
   *
   * CustomGroup deletes always reconcile (to clean up the removed group's
   * artifacts, since it can no longer be inspected). Otherwise the group — or
   * the changed field's group — must be one of our repeatable Case groups, so
   * that editing unrelated custom data does not trigger a reconcile.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   * @param object $objectRef
   *   Object reference.
   *
   * @return bool
   *   TRUE when the managed artifacts should be reconciled.
   */
  private function shouldRun($op, $objectName, $objectRef): bool {
    if (!in_array($op, ['create', 'edit', 'delete'], TRUE)) {
      return FALSE;
    }

    $service = new CaseCustomAfforms();

    if ($objectName === 'CustomGroup') {
      if ($op === 'delete') {
        return TRUE;
      }
      $names = array_column($service->getRepeatableCaseGroups(), 'name');
      return in_array($objectRef->name ?? NULL, $names, TRUE);
    }

    if ($objectName === 'CustomField') {
      $groupId = $objectRef->custom_group_id ?? NULL;
      if (empty($groupId)) {
        return FALSE;
      }
      $groups = $service->getRepeatableCaseGroups();
      $ids = array_map('intval', array_column($groups, 'id'));
      return in_array((int) $groupId, $ids, TRUE);
    }

    return FALSE;
  }

}
