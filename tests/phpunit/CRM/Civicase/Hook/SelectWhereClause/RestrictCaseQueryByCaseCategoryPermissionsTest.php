<?php

use CRM_Case_BAO_CaseType as CaseType;
use CRM_Civicase_Hook_SelectWhereClause_RestrictCaseQueryByCaseCategoryPermissions as RestrictCaseQuery;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;
use CRM_Civicase_Test_Fabricator_Case as CaseFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;
use CRM_Civicase_Test_Fabricator_Relationship as RelationshipFabricator;
use CRM_Civicase_Test_Fabricator_RelationshipType as RelationshipTypeFabricator;
use Civi\Api4\Activity;
use Civi\Api4\CiviCase;

/**
 * Tests the case category aware case ACL clause.
 *
 * @group headless
 */
class CRM_Civicase_Hook_SelectWhereClause_RestrictCaseQueryByCaseCategoryPermissionsTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Fabricated case client.
   *
   * @var array
   */
  private static $client;

  /**
   * Fabricated contact that holds a case role on some cases.
   *
   * @var array
   */
  private static $caseManager;

  /**
   * Fabricated contact that holds a case role on the other cases.
   *
   * @var array
   */
  private static $otherManager;

  /**
   * Fabricated relationship type used for the case role.
   *
   * @var array
   */
  private static $relationshipType;

  /**
   * Fabricated case types.
   *
   * @var array
   */
  private static $caseTypes = [];

  /**
   * Fabricated cases, keyed by name.
   *
   * @var array
   */
  private static $cases = [];

  /**
   * Creates two "Cases" cases and two "award" cases for the same client.
   *
   * The case manager holds a case role on one case of each category, and the
   * other manager on the other case of each category.
   */
  public static function setUpBeforeClass(): void {
    // An earlier test class can leave a rolled back contact as the session
    // user, which breaks the change log of the contacts created here.
    CRM_Core_Session::singleton()->set('userID', NULL);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => 'case_type_categories',
      'name' => 'award',
      'value' => 2,
    ]);

    static::$caseTypes['cases'] = CaseTypeFabricator::fabricate([
      'name' => 'Case_category_acl_cases',
      'case_type_category' => 1,
    ]);
    static::$caseTypes['award'] = CaseTypeFabricator::fabricate([
      'name' => 'Case_category_acl_award',
      'case_type_category' => 2,
    ]);
    static::$client = ContactFabricator::fabricate();
    static::$caseManager = ContactFabricator::fabricate();
    static::$otherManager = ContactFabricator::fabricate();
    static::$relationshipType = RelationshipTypeFabricator::fabricate([
      'name_a_b' => 'Case category ACL manager is',
      'name_b_a' => 'Case category ACL manager of',
    ]);

    $caseTypeByCase = [
      'my_case' => 'cases',
      'other_case' => 'cases',
      'my_award' => 'award',
      'other_award' => 'award',
    ];
    foreach ($caseTypeByCase as $caseName => $caseTypeName) {
      static::$cases[$caseName] = CaseFabricator::fabricate([
        'case_type_id' => static::$caseTypes[$caseTypeName]['id'],
        'contact_id' => static::$client['id'],
        'creator_id' => static::$client['id'],
      ]);
    }

    $managerByCase = [
      'my_case' => static::$caseManager,
      'my_award' => static::$caseManager,
      'other_case' => static::$otherManager,
      'other_award' => static::$otherManager,
    ];
    foreach ($managerByCase as $caseName => $manager) {
      RelationshipFabricator::fabricate([
        'contact_id_a' => static::$client['id'],
        'contact_id_b' => $manager['id'],
        'relationship_type_id' => static::$relationshipType['id'],
        'case_id' => static::$cases[$caseName]['id'],
      ]);
    }
  }

  /**
   * Cleans the cases, case types and relationship type created.
   */
  public static function tearDownAfterClass(): void {
    foreach (static::$cases as $case) {
      civicrm_api3('Case', 'delete', ['id' => $case['id']]);
    }

    foreach (static::$caseTypes as $caseType) {
      civicrm_api3('CaseType', 'delete', ['id' => $caseType['id']]);
    }

    civicrm_api3('RelationshipType', 'delete', ['id' => static::$relationshipType['id']]);
  }

  /**
   * Logs in as the case manager before each test.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession(static::$caseManager['id']);
  }

  /**
   * Logs out, as some tests log in a contact that is rolled back.
   */
  public function tearDown(): void {
    $this->unregisterCurrentLoggedInContactFromSession();
  }

  /**
   * Access all permission returns every case of the category.
   */
  public function testAllPermissionReturnsEveryCaseOfTheCategory() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic award information',
      'access all award and activities',
    ]);

    $this->assertEquals(
      $this->getCaseIds(['my_award', 'other_award']),
      $this->getAwardCaseIds()
    );
  }

  /**
   * Access my permission returns only the cases with a case role.
   */
  public function testMyPermissionReturnsOnlyCasesWithCaseRole() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic award information',
      'access my award and activities',
    ]);

    $this->assertEquals($this->getCaseIds(['my_award']), $this->getAwardCaseIds());
  }

  /**
   * Access my permission returns nothing to a contact without a case role.
   */
  public function testMyPermissionReturnsNoCasesWithoutCaseRole() {
    $this->registerCurrentLoggedInContactInSession(ContactFabricator::fabricate()['id']);
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic award information',
      'access my award and activities',
    ]);

    $this->assertEquals([], $this->getAwardCaseIds());
  }

  /**
   * Basic information permission alone returns every case of the category.
   */
  public function testBasicPermissionReturnsEveryCaseOfTheCategory() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic award information',
    ]);

    $this->assertEquals(
      $this->getCaseIds(['my_award', 'other_award']),
      $this->getAwardCaseIds()
    );
  }

  /**
   * Permissions for one category give no cases of another category.
   */
  public function testCasesOfCategoryWithoutPermissionsAreNotReturned() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic case information',
      'access all cases and activities',
    ]);

    $result = civicrm_api3('Case', 'get', [
      'id' => ['IN' => $this->getCaseIds(['my_case', 'my_award', 'other_award'])],
      'check_permissions' => 1,
    ]);

    $this->assertEquals($this->getCaseIds(['my_case']), array_keys($result['values']));
  }

  /**
   * No case permission in any category gives no cases.
   */
  public function testNoCasePermissionGivesNoCases() {
    $this->setPermissions(['access CiviCRM', 'view all contacts']);
    $clauses = ['id' => []];

    (new RestrictCaseQuery())->run('Case', $clauses);

    $this->assertEquals(['IS NULL'], $clauses['id']);
  }

  /**
   * The case client must be visible through the contact ACL.
   */
  public function testCasesOfClientsTheContactCannotViewAreNotReturned() {
    $this->setPermissions([
      'access CiviCRM',
      'basic award information',
      'access all award and activities',
    ]);

    $this->assertEquals([], $this->getAwardCaseIds());
  }

  /**
   * Access all permission in every category adds no case id clause.
   */
  public function testAllPermissionInEveryCategoryAddsNoClause() {
    $caseCategoryPermission = new CaseCategoryPermission();
    // With deleted contacts too, the contact ACL adds no client clause.
    $permissions = ['access CiviCRM', 'view all contacts', 'access deleted contacts'];
    foreach (CaseType::buildOptions('case_type_category', 'validate') as $categoryName) {
      $permissions[] = $caseCategoryPermission->replaceWords('access all cases and activities', $categoryName);
    }
    $this->setPermissions($permissions);
    $clauses = ['id' => []];

    (new RestrictCaseQuery())->run('Case', $clauses);

    $this->assertEquals([], $clauses['id']);
  }

  /**
   * Clauses of other entities are not changed.
   */
  public function testOtherEntitiesAreNotChanged() {
    $this->setPermissions(['access CiviCRM']);
    $clauses = ['id' => ['= 1']];

    (new RestrictCaseQuery())->run('Activity', $clauses);

    $this->assertEquals(['id' => ['= 1']], $clauses);
  }

  /**
   * Access my in one category and access all in another are combined.
   */
  public function testMixedCategoryPermissionsCombineAllAndMyAccess() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic case information',
      'access my cases and activities',
      'basic award information',
      'access all award and activities',
    ]);

    $this->assertEquals(
      $this->getCaseIds(['my_case', 'my_award', 'other_award']),
      $this->getVisibleCaseIds(['my_case', 'other_case', 'my_award', 'other_award'])
    );
  }

  /**
   * API4 returns the same cases as API3 for "my" access in "Cases".
   */
  public function testApi4ReturnsTheSameCasesAsApi3() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic case information',
      'access my cases and activities',
    ]);

    $api4Ids = CiviCase::get(TRUE)
      ->addSelect('id')
      ->addWhere('id', 'IN', $this->getCaseIds(['my_case', 'other_case']))
      ->execute()
      ->column('id');
    $api4Ids = array_map('intval', $api4Ids);
    sort($api4Ids);
    $api3Ids = $this->getVisibleCaseIds(['my_case', 'other_case']);

    $this->assertEquals($this->getCaseIds(['my_case']), $api3Ids);
    $this->assertEquals($api3Ids, $api4Ids);
  }

  /**
   * A category in the URL does not open cases of other categories.
   */
  public function testUrlCategoryDoesNotGrantCasesOfOtherCategories() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic award information',
      'access all award and activities',
    ]);
    $originalGet = $_GET;
    $originalRequest = $_REQUEST;
    $_GET['q'] = 'civicrm/case/a';
    $_GET['case_type_category'] = $_REQUEST['case_type_category'] = 'award';

    try {
      $isUrlRewriteActive = CRM_Core_Permission::check('access all cases and activities');
      $caseIds = $this->getVisibleCaseIds(['my_case', 'other_case', 'my_award', 'other_award']);
      $hookClauses = ['id' => []];
      (new RestrictCaseQuery())->run('Case', $hookClauses);
      $hookCaseIds = $this->getCaseIdsMatchingClauses($hookClauses, ['my_case', 'other_case', 'my_award', 'other_award']);
    }
    finally {
      $_GET = $originalGet;
      $_REQUEST = $originalRequest;
    }

    $this->assertTrue($isUrlRewriteActive);
    $this->assertEquals($this->getCaseIds(['my_award', 'other_award']), $caseIds);
    $this->assertEquals($this->getCaseIds(['my_award', 'other_award']), $hookCaseIds);
  }

  /**
   * An explicit contact id is used for the permissions and the case roles.
   */
  public function testExplicitContactIdIsUsedForPermissionsAndCaseRoles() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic award information',
      'access my award and activities',
    ]);
    $casePermissions = [];
    $caseCategoryPermission = new CaseCategoryPermission();
    foreach (CaseType::buildOptions('case_type_category', 'validate') as $categoryName) {
      foreach (['access all cases and activities', 'access my cases and activities', 'basic case information'] as $permission) {
        $casePermissions[] = $caseCategoryPermission->replaceWords($permission, $categoryName);
      }
    }
    $checkedContactIds = [];
    $recordCheck = function ($event) use ($casePermissions, &$checkedContactIds) {
      if (in_array($event->permission, $casePermissions)) {
        $checkedContactIds[] = (int) $event->contactId;
      }
    };
    $clauses = ['id' => []];

    Civi::dispatcher()->addListener('hook_civicrm_permission_check', $recordCheck);
    try {
      (new RestrictCaseQuery())->run('Case', $clauses, static::$otherManager['id']);
    }
    finally {
      Civi::dispatcher()->removeListener('hook_civicrm_permission_check', $recordCheck);
    }

    $this->assertNotEmpty($checkedContactIds);
    $this->assertEquals([(int) static::$otherManager['id']], array_values(array_unique($checkedContactIds)));
    $this->assertEquals(
      $this->getCaseIds(['other_award']),
      $this->getCaseIdsMatchingClauses($clauses, ['my_case', 'other_case', 'my_award', 'other_award'])
    );
  }

  /**
   * The select where clause hook builds the case clause for the given user.
   */
  public function testSelectWhereClauseHookUsesTheGivenUserId() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic award information',
      'access my award and activities',
    ]);
    $clauses = ['id' => []];

    CRM_Utils_Hook::selectWhereClause('Case', $clauses, (int) static::$otherManager['id']);

    $this->assertEquals(
      $this->getCaseIds(['other_award']),
      $this->getCaseIdsMatchingClauses($clauses, ['my_case', 'other_case', 'my_award', 'other_award'])
    );
  }

  /**
   * Access my permission returns only the activities of case role cases.
   */
  public function testMyPermissionReturnsOnlyActivitiesOfCasesWithCaseRole() {
    $activityIds = [];
    foreach (['my_case', 'other_case'] as $caseName) {
      $activityIds[$caseName] = (int) civicrm_api3('Activity', 'create', [
        'activity_type_id' => 'Meeting',
        'case_id' => static::$cases[$caseName]['id'],
        'source_contact_id' => static::$client['id'],
        'target_id' => static::$client['id'],
        'subject' => 'Case category ACL activity',
      ])['id'];
    }
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic case information',
      'access my cases and activities',
    ]);

    $visibleIds = Activity::get(TRUE)
      ->addSelect('id')
      ->addWhere('id', 'IN', array_values($activityIds))
      ->execute()
      ->column('id');

    $this->assertEquals([$activityIds['my_case']], array_map('intval', $visibleIds));
  }

  /**
   * Returns the given fabricated cases that API3 returns to the current user.
   *
   * @param string[] $caseNames
   *   Case names.
   *
   * @return int[]
   *   Sorted case ids.
   */
  private function getVisibleCaseIds(array $caseNames) {
    $result = civicrm_api3('Case', 'get', [
      'id' => ['IN' => $this->getCaseIds($caseNames)],
      'check_permissions' => 1,
    ]);
    $ids = array_map('intval', array_keys($result['values']));
    sort($ids);

    return $ids;
  }

  /**
   * Returns the given fabricated cases that match the where clauses.
   *
   * @param array $clauses
   *   Where clauses, as built by the select where clause hook.
   * @param string[] $caseNames
   *   Case names.
   *
   * @return int[]
   *   Sorted case ids.
   */
  private function getCaseIdsMatchingClauses(array $clauses, array $caseNames) {
    $where = ['id IN (' . implode(',', $this->getCaseIds($caseNames)) . ')'];
    foreach ($clauses as $field => $conditions) {
      foreach ($conditions as $condition) {
        $where[] = "$field $condition";
      }
    }
    $rows = CRM_Core_DAO::executeQuery('SELECT id FROM civicrm_case WHERE ' . implode(' AND ', $where))->fetchAll();
    $ids = array_map('intval', array_column($rows, 'id'));
    sort($ids);

    return $ids;
  }

  /**
   * Returns the award cases of the client, as the case dashboard asks.
   *
   * @return int[]
   *   Sorted case ids.
   */
  private function getAwardCaseIds() {
    $result = civicrm_api3('Case', 'getcaselist', [
      'contact_id' => static::$client['id'],
      'case_type_id.case_type_category' => ['IN' => ['award']],
      'check_permissions' => 1,
      'sequential' => 1,
    ]);
    $ids = array_map('intval', array_column($result['values'], 'id'));
    sort($ids);

    return $ids;
  }

  /**
   * Returns the sorted ids of the given fabricated cases.
   *
   * @param string[] $caseNames
   *   Case names.
   *
   * @return int[]
   *   Sorted case ids.
   */
  private function getCaseIds(array $caseNames) {
    $ids = [];
    foreach ($caseNames as $caseName) {
      $ids[] = (int) static::$cases[$caseName]['id'];
    }
    sort($ids);

    return $ids;
  }

}
