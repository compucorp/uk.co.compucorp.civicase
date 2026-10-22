<?php

/**
 * Session Helper trait.
 */
trait CRM_Civicase_Helpers_SessionTrait {

  /**
   * Register contact in session.
   *
   * @param int $contactID
   *   Contact Id.
   */
  private function registerCurrentLoggedInContactInSession($contactID) {
    $session = CRM_Core_Session::singleton();
    $session->set('userID', $contactID);
  }

  /**
   * Unregister contact from session.
   */
  private function unregisterCurrentLoggedInContactFromSession() {
    $session = CRM_Core_Session::singleton();
    $session->set('userID', NULL);
  }

  /**
   * Set permissions in session.
   *
   * @param array $permissions
   *   Permissions.
   */
  private function setPermissions(array $permissions = []) {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = $permissions;
    $this->clearCachedCaseCategoryAccess();
  }

  /**
   * Resets the permissions, so every permission is granted again.
   */
  private function resetPermissions() {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = NULL;
    $this->clearCachedCaseCategoryAccess();
  }

  /**
   * Clears the case category access cached for each contact.
   */
  private function clearCachedCaseCategoryAccess() {
    unset(Civi::$statics[CRM_Civicase_Hook_SelectWhereClause_RestrictCaseQueryByCaseCategoryPermissions::class]);
  }

}
