<?php

use CRM_Case_BAO_CaseType as CaseType;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;

/**
 * Applies the core case ACL per case category.
 *
 * Core's clause (CRM_Case_BAO_Case::addSelectWhereClause) only checks the
 * generic case permissions, which users of other case categories do not have.
 * This replaces it with the same rules, checked for each case category.
 */
class CRM_Civicase_Hook_SelectWhereClause_RestrictCaseQueryByCaseCategoryPermissions {

  /**
   * Replaces the core case id clause with a case category aware one.
   *
   * @param string $entity
   *   Entity name.
   * @param array $clauses
   *   Where clauses.
   * @param int|null $contactId
   *   Contact to build the clause for. Defaults to the logged in contact.
   */
  public function run($entity, array &$clauses, $contactId = NULL) {
    if ($entity !== 'Case') {
      return;
    }

    $contactId = $contactId ?: CRM_Core_Session::getLoggedInContactID();
    $caseTypeCategories = CaseType::buildOptions('case_type_category', 'validate');
    $fullAccessCategoryIds = [];
    $myAccessCategoryIds = [];

    foreach ($caseTypeCategories as $categoryId => $categoryName) {
      if ($this->hasPermission('access all cases and activities', $categoryName, $contactId)) {
        $fullAccessCategoryIds[] = (int) $categoryId;
      }
      elseif ($this->hasPermission('access my cases and activities', $categoryName, $contactId)) {
        $myAccessCategoryIds[] = (int) $categoryId;
      }
      elseif ($this->hasPermission('basic case information', $categoryName, $contactId)) {
        $fullAccessCategoryIds[] = (int) $categoryId;
      }
    }

    $clauses['id'] = [];

    if (empty($fullAccessCategoryIds) && empty($myAccessCategoryIds)) {
      $clauses['id'][] = 'IS NULL';

      return;
    }

    // Enforce permission to view the case client, as core does.
    $contactClause = CRM_Utils_SQL::mergeSubquery('Contact');
    if ($contactClause) {
      $contactClause = implode(' AND contact_id ', $contactClause);
      $clauses['id'][] = "IN (SELECT case_id FROM civicrm_case_contact WHERE contact_id $contactClause)";
    }

    if (count($fullAccessCategoryIds) === count($caseTypeCategories)) {
      return;
    }

    $conditions = [];
    if ($fullAccessCategoryIds) {
      $conditions[] = 'ct.case_type_category IN (' . implode(',', $fullAccessCategoryIds) . ')';
    }
    if ($myAccessCategoryIds) {
      $conditions[] = '(ct.case_type_category IN (' . implode(',', $myAccessCategoryIds) . ') AND c.id ' . $this->getMyCasesClause((int) $contactId) . ')';
    }

    $clauses['id'][] = 'IN (SELECT c.id FROM civicrm_case c INNER JOIN civicrm_case_type ct ON ct.id = c.case_type_id WHERE ' . implode(' OR ', $conditions) . ')';
  }

  /**
   * Checks a case permission for the given case category.
   *
   * The contact id is passed so the permission is not rewritten to the
   * category found in the current URL.
   *
   * @param string $permission
   *   Generic case permission.
   * @param string $categoryName
   *   Case category name.
   * @param int|null $contactId
   *   Contact id.
   *
   * @return bool
   *   Whether the contact has the permission for the category.
   */
  private function hasPermission($permission, $categoryName, $contactId) {
    $categoryPermission = (new CaseCategoryPermission())->replaceWords($permission, $categoryName);

    return CRM_Core_Permission::check($categoryPermission, $contactId);
  }

  /**
   * Returns the clause for cases where the contact holds a case role.
   *
   * Same as the private CRM_Case_BAO_Case::getAccessMyCasesClause().
   *
   * @param int $contactId
   *   Contact id.
   *
   * @return string
   *   Clause for the case id.
   */
  private function getMyCasesClause($contactId) {
    return "IN (
      SELECT r.case_id FROM civicrm_relationship r, civicrm_case_contact cc WHERE r.is_active = 1 AND cc.case_id = r.case_id AND (
        (r.contact_id_a = cc.contact_id AND r.contact_id_b = $contactId) OR (r.contact_id_b = cc.contact_id AND r.contact_id_a = $contactId)
      )
    )";
  }

}
