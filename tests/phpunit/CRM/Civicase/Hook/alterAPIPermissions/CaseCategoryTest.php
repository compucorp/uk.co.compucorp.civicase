<?php

use CRM_Civicase_Hook_AlterAPIPermissions_CaseCategory as CaseCategoryAlterApiPermissions;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;
use CRM_Civicase_Test_Fabricator_Case as CaseFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;

/**
 * Tests for case category API permission alterations.
 *
 * @group headless
 */
class CRM_Civicase_Hook_AlterAPIPermissions_CaseCategoryTest extends BaseHeadlessTest {

  /**
   * Tests activity entity resolves case category using CaseActivity mapping.
   */
  public function testActivityEntityTypeWithLinkedCaseUsesLinkedCaseCategoryPermissions() {
    $fixtures = $this->createFixtures();

    try {
      $permissions = $this->getInitialPermissions();
      $params = [
        'entity_type' => 'Activity',
        'entity_id' => $fixtures['linkedActivityId'],
      ];

      (new CaseCategoryAlterApiPermissions())->run('custom_value', 'gettreevalues', $params, $permissions);

      $this->assertEquals(
        (new CaseCategoryPermission())->getBasicCasePermissions($fixtures['caseCategoryName']),
        $permissions['custom_value']['gettreevalues'][0]
      );
    }
    finally {
      $this->cleanupFixtures($fixtures);
    }
  }

  /**
   * Tests activity with no linked case returns default category permissions.
   */
  public function testActivityEntityTypeWithoutLinkedCaseReturnsDefaultPermissions() {
    $fixtures = $this->createFixtures();

    try {
      $permissions = $this->getInitialPermissions();
      $params = [
        'entity_type' => 'Activity',
        'entity_id' => $fixtures['unlinkedActivityId'],
      ];

      (new CaseCategoryAlterApiPermissions())->run('custom_value', 'gettreevalues', $params, $permissions);

      $this->assertEquals(
        (new CaseCategoryPermission())->getBasicCasePermissions(NULL),
        $permissions['custom_value']['gettreevalues'][0]
      );
    }
    finally {
      $this->cleanupFixtures($fixtures);
    }
  }

  /**
   * Tests unsupported entity type returns default category permissions.
   */
  public function testUnsupportedEntityTypeReturnsDefaultPermissions() {
    $fixtures = $this->createFixtures();

    try {
      $permissions = $this->getInitialPermissions();
      $params = [
        'entity_type' => 'Contact',
        'entity_id' => 1,
      ];

      (new CaseCategoryAlterApiPermissions())->run('custom_value', 'gettreevalues', $params, $permissions);

      $this->assertEquals(
        (new CaseCategoryPermission())->getBasicCasePermissions(NULL),
        $permissions['custom_value']['gettreevalues'][0]
      );
    }
    finally {
      $this->cleanupFixtures($fixtures);
    }
  }

  /**
   * Tests missing entity type falls back to legacy behavior.
   */
  public function testMissingEntityTypeFallsBackToLegacyCaseIdBehavior() {
    $fixtures = $this->createFixtures();

    try {
      $permissions = $this->getInitialPermissions();
      $params = [
        'entity_id' => $fixtures['caseId'],
      ];

      (new CaseCategoryAlterApiPermissions())->run('custom_value', 'gettreevalues', $params, $permissions);

      $this->assertEquals(
        (new CaseCategoryPermission())->getBasicCasePermissions($fixtures['caseCategoryName']),
        $permissions['custom_value']['gettreevalues'][0]
      );
    }
    finally {
      $this->cleanupFixtures($fixtures);
    }
  }

  /**
   * Create all fixture data required for a test.
   *
   * @return array
   *   Fixture identifiers.
   */
  private function createFixtures() {
    $testSuffix = strtolower(substr(md5(__METHOD__ . microtime(TRUE) . mt_rand()), 0, 12));

    $caseCategoryResult = civicrm_api3('OptionValue', 'create', [
      'option_group_id' => 'case_type_categories',
      'name' => "test_award_{$testSuffix}",
      'label' => "Test Award {$testSuffix}",
      'is_active' => 1,
    ]);
    $caseCategory = $this->getApi3Entity($caseCategoryResult);

    $caseType = CaseTypeFabricator::fabricate([
      'name' => "test_award_case_type_{$testSuffix}",
      'title' => "Test Award Case Type {$testSuffix}",
      'case_type_category' => $caseCategory['value'],
    ]);

    $contactResult = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'CaseCategory',
      'last_name' => "Test",
      'email' => "casecategory@example.org",
    ]);
    $contact = $this->getApi3Entity($contactResult);

    $case = CaseFabricator::fabricate([
      'case_type_id' => $caseType['id'],
      'contact_id' => $contact['id'],
      'creator_id' => $contact['id'],
    ]);

    $linkedActivityResult = civicrm_api3('Activity', 'create', [
      'case_id' => $case['id'],
      'source_contact_id' => $contact['id'],
      'activity_type_id' => 'Assign Case Role',
      'subject' => "Linked activity {$testSuffix}",
    ]);
    $linkedActivity = $this->getApi3Entity($linkedActivityResult);

    $unlinkedActivityResult = civicrm_api3('Activity', 'create', [
      'source_contact_id' => $contact['id'],
      'activity_type_id' => 'Meeting',
      'subject' => "Unlinked activity {$testSuffix}",
    ]);
    $unlinkedActivity = $this->getApi3Entity($unlinkedActivityResult);

    return [
      'caseCategoryId' => $caseCategory['id'],
      'caseCategoryName' => $caseCategory['name'],
      'caseTypeId' => $caseType['id'],
      'contactId' => $contact['id'],
      'caseId' => $case['id'],
      'linkedActivityId' => $linkedActivity['id'],
      'unlinkedActivityId' => $unlinkedActivity['id'],
    ];
  }

  /**
   * Cleanup fixture data.
   *
   * @param array $fixtures
   *   Fixture identifiers.
   */
  private function cleanupFixtures(array $fixtures) {
    foreach (['linkedActivityId', 'unlinkedActivityId'] as $activityKey) {
      if (!empty($fixtures[$activityKey])) {
        civicrm_api3('Activity', 'delete', ['id' => $fixtures[$activityKey]]);
      }
    }

    if (!empty($fixtures['caseId'])) {
      civicrm_api3('Case', 'delete', ['id' => $fixtures['caseId']]);
    }

    if (!empty($fixtures['caseTypeId'])) {
      civicrm_api3('CaseType', 'delete', ['id' => $fixtures['caseTypeId']]);
    }

    if (!empty($fixtures['contactId'])) {
      civicrm_api3('Contact', 'delete', ['id' => $fixtures['contactId']]);
    }

    if (!empty($fixtures['caseCategoryId'])) {
      civicrm_api3('OptionValue', 'delete', ['id' => $fixtures['caseCategoryId']]);
    }
  }

  /**
   * Returns the first entity payload from an API3 response.
   *
   * @param array $result
   *   API3 response.
   *
   * @return array
   *   Entity payload.
   */
  private function getApi3Entity(array $result) {
    if (!empty($result['values']) && is_array($result['values'])) {
      return (array) array_shift($result['values']);
    }

    return $result;
  }

  /**
   * Returns initial permissions array expected by hook.
   *
   * @return array
   *   The permissions array.
   */
  private function getInitialPermissions() {
    return [
      'default' => ['default' => ['access CiviCRM']],
      'case' => [
        'create' => ['add cases'],
        'delete' => ['delete in CiviCase'],
      ],
      'relationship_type' => [
        'get' => [['access CiviCRM']],
      ],
      'custom_value' => [
        'gettreevalues' => [['access CiviCRM']],
      ],
    ];
  }

}
