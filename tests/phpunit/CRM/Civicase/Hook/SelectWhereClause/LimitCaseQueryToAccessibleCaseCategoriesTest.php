<?php

use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;
use CRM_Civicase_Test_Fabricator_Case as CaseFabricator;
use CRM_Civicase_Test_Fabricator_Relationship as RelationshipFabricator;
use CRM_Civicase_Test_Fabricator_RelationshipType as RelationshipTypeFabricator;

/**
 * Tests for limiting case query to accessible case categories.
 *
 * @group headless
 */
class CRM_Civicase_Hook_SelectWhereClause_LimitCaseQueryToAccessibleCaseCategoriesTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Fabricated client.
   *
   * @var array
   */
  private static $client;

  /**
   * Fabricated case types.
   *
   * @var array
   */
  private static $caseTypes;

  /**
   * Fabricated cases.
   *
   * @var array
   */
  private static $cases;

  /**
   * Setup case types and cases before running of tests.
   */
  public static function setUpBeforeClass(): void {
    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => 'case_type_categories',
      'name' => 'award',
      'value' => 2,
    ]);

    static::$caseTypes[] = CaseTypeFabricator::fabricate([
      'name' => 'Case_type_first',
      'case_type_category' => 1,
    ]);

    static::$caseTypes[] = CaseTypeFabricator::fabricate([
      'name' => 'Case_type_second',
      'case_type_category' => 2,
    ]);
    static::$client = ContactFabricator::fabricate();

    static::$cases[] = CaseFabricator::fabricate(
      [
        'case_type_id' => static::$caseTypes[0]['id'],
        'contact_id' => static::$client['id'],
        'creator_id' => static::$client['id'],
      ]
    );
    static::$cases[] = CaseFabricator::fabricate(
      [
        'case_type_id' => static::$caseTypes[1]['id'],
        'contact_id' => static::$client['id'],
        'creator_id' => static::$client['id'],
      ]
    );
  }

  /**
   * Clean the cases and types created.
   */
  public static function tearDownAfterClass(): void {
    foreach (static::$cases as $case) {
      civicrm_api3('Case', 'delete', [
        'id' => $case['id'],
      ]);
    }

    foreach (static::$caseTypes as $caseType) {
      civicrm_api3('CaseType', 'delete', [
        'id' => $caseType['id'],
      ]);
    }
  }

  /**
   * Logs out and resets the permissions, so later tests are not affected.
   *
   * Each test logs in a contact that is rolled back.
   */
  public function tearDown(): void {
    $this->unregisterCurrentLoggedInContactFromSession();
    $this->resetPermissions();
  }

  /**
   * Test that all records are returned if user has no basic permissions.
   */
  public function testAllCasesAreReturnedForNoBasicPermissions() {
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
    $this->setPermissions([
      'view all contacts',
      'access all cases and activities',
      'access all award and activities',
    ]);

    $result = civicrm_api3('Case', 'get', [
      'contact_id' => static::$client['id'],
      'check_permissions' => 1,
    ]);

    $this->assertEquals($result['count'], 2);
  }

  /**
   * Test that all records are returned if user has all permissions.
   */
  public function testAllCasesAreReturnedForAllPermissions() {
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
    $this->setPermissions([
      'view all contacts',
      'basic case information',
      'basic award information',
    ]);

    $result = civicrm_api3('Case', 'get', [
      'contact_id' => static::$client['id'],
      'check_permissions' => 1,
    ]);

    $this->assertEquals($result['count'], 2);
  }

  /**
   * Test that only accessible records are returned for limited permissions.
   */
  public function testOnlyAccessibleCasesAreReturnedForLimitedPermissions() {
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
    $this->setPermissions(['view all contacts', 'basic case information']);

    $result = civicrm_api3('Case', 'get', [
      'contact_id' => static::$client['id'],
      'check_permissions' => 1,
      'sequential' => 1,
    ]);

    $this->assertEquals($result['count'], 1);
    $this->assertEquals($result['values'][0]['case_type_id'], static::$caseTypes[0]['id']);
  }

  /**
   * Test that cases of a category without basic information are not returned.
   *
   * The contact can view the client and holds a case role on the award case,
   * so only the missing "basic award information" permission hides it.
   */
  public function testNoCasesAreReturnedWhenUserTriesToFetchCasesForCategoryItDoesNotHaveAccessTo() {
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
    $relationshipType = RelationshipTypeFabricator::fabricate([
      'name_a_b' => 'Limit case query manager is',
      'name_b_a' => 'Limit case query manager of',
    ]);
    RelationshipFabricator::fabricate([
      'contact_id_a' => static::$client['id'],
      'contact_id_b' => $contact['id'],
      'relationship_type_id' => $relationshipType['id'],
      'case_id' => static::$cases[1]['id'],
    ]);
    $this->setPermissions([
      'view all contacts',
      'basic case information',
      'access CiviCRM',
      'access my award and activities',
    ]);

    $result = civicrm_api3('Case', 'getcaselist', [
      'contact_id' => static::$client['id'],
      'case_type_id.case_type_category' => [
        'IN' => ['award'],
      ],
      'check_permissions' => 1,
      'sequential' => 1,
    ]);

    $this->assertEquals($result['count'], 0);
  }

  /**
   * Test that only accessible records are returned for limited permissions.
   */
  public function testCasesAreReturnedOnlyForCasesUserHasAccessToWhenCaseCategoryParameterIsSent() {
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
    $this->setPermissions([
      'view all contacts',
      'basic award information',
      'access CiviCRM',
      'access all award and activities',
    ]);

    $result = civicrm_api3('Case', 'getcaselist', [
      'contact_id' => static::$client['id'],
      'case_type_id.case_type_category' => [
        'IN' => ['award'],
      ],
      'check_permissions' => 1,
      'sequential' => 1,
    ]);

    $this->assertEquals($result['count'], 1);
    $this->assertEquals($result['values'][0]['case_type_id'], static::$caseTypes[1]['id']);
  }

}
