<?php

use CRM_Civicase_Test_Fabricator_Case as CaseFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;
use CRM_Civicase_Test_Fabricator_Relationship as RelationshipFabricator;
use CRM_Civicase_Test_Fabricator_RelationshipType as RelationshipTypeFabricator;
use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\CaseActivity;
use Civi\Api4\CaseContact;
use Civi\Api4\CiviCase;
use Civi\Api4\Contact;

/**
 * Tests API4 case reads for case category users.
 *
 * @group headless
 */
class CRM_Civicase_Event_Listener_CaseCategoryApiAuthorizationTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Permissions of a user with "my" and basic access to awards.
   */
  const MY_AWARD_PERMISSIONS = [
    'access CiviCRM',
    'view all contacts',
    'basic award information',
    'access my award and activities',
  ];

  /**
   * Names of all the fabricated cases.
   */
  const ALL_CASES = ['my_case', 'other_case', 'my_award', 'other_award'];

  /**
   * Fabricated case client.
   *
   * @var array
   */
  private static $client;

  /**
   * Fabricated contact that holds a case role on the "my" cases.
   *
   * @var array
   */
  private static $caseManager;

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
   * Whether this class created the "award" case category.
   *
   * @var bool
   */
  private static $createdAwardCategory = FALSE;

  /**
   * Creates two "Cases" cases and two "award" cases for the same client.
   *
   * The case manager holds a case role on one case of each category.
   */
  public static function setUpBeforeClass(): void {
    CRM_Core_Session::singleton()->set('userID', NULL);

    $awardCategory = [
      'option_group_id' => 'case_type_categories',
      'name' => 'award',
    ];
    $awardCount = civicrm_api3('OptionValue', 'getcount', $awardCategory);
    static::$createdAwardCategory = (int) $awardCount === 0;
    CRM_Core_BAO_OptionValue::ensureOptionValueExists(
      $awardCategory + ['value' => 2]
    );

    static::$caseTypes['cases'] = CaseTypeFabricator::fabricate([
      'name' => 'Case_category_api4_cases',
      'case_type_category' => 1,
    ]);
    static::$caseTypes['award'] = CaseTypeFabricator::fabricate([
      'name' => 'Case_category_api4_award',
      'case_type_category' => 2,
    ]);
    static::$client = ContactFabricator::fabricate();
    static::$caseManager = ContactFabricator::fabricate();
    static::$relationshipType = RelationshipTypeFabricator::fabricate([
      'name_a_b' => 'Case category API4 manager is',
      'name_b_a' => 'Case category API4 manager of',
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

    foreach (['my_case', 'my_award'] as $caseName) {
      RelationshipFabricator::fabricate([
        'contact_id_a' => static::$client['id'],
        'contact_id_b' => static::$caseManager['id'],
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

    civicrm_api3('RelationshipType', 'delete', [
      'id' => static::$relationshipType['id'],
    ]);

    // Later test classes number their case categories from the free values.
    if (static::$createdAwardCategory) {
      $awardCategoryId = civicrm_api3('OptionValue', 'getvalue', [
        'option_group_id' => 'case_type_categories',
        'name' => 'award',
        'return' => 'id',
      ]);
      civicrm_api3('OptionValue', 'delete', ['id' => $awardCategoryId]);
      CRM_Core_PseudoConstant::flush();
    }
  }

  /**
   * Logs in as the case manager before each test.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession(static::$caseManager['id']);
  }

  /**
   * Logs out and resets the permissions, so later tests are not affected.
   */
  public function tearDown(): void {
    $this->unregisterCurrentLoggedInContactFromSession();
    $this->resetPermissions();
  }

  /**
   * Access my and basic permissions return only the cases with a case role.
   */
  public function testMyAndBasicPermissionsReturnOnlyCasesWithCaseRole() {
    $this->setPermissions(self::MY_AWARD_PERMISSIONS);

    $this->assertEquals(
      $this->getCaseIds(['my_award']),
      $this->getApi4CaseIds()
    );
  }

  /**
   * A contact without a case role gets no cases.
   */
  public function testContactWithoutCaseRoleGetsNoCases() {
    $contactId = ContactFabricator::fabricate()['id'];
    $this->registerCurrentLoggedInContactInSession($contactId);
    $this->setPermissions(self::MY_AWARD_PERMISSIONS);

    $this->assertEquals([], $this->getApi4CaseIds());
  }

  /**
   * Access all permission returns the cases of the category the contact sees.
   */
  public function testAllPermissionReturnsCasesOfTheCategoryWithinContactAcl() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'access all award and activities',
    ]);
    $withContactAccess = $this->getApi4CaseIds();

    $this->setPermissions(['access CiviCRM', 'access all award and activities']);
    $withoutContactAccess = $this->getApi4CaseIds();

    $this->assertEquals(
      $this->getCaseIds(['my_award', 'other_award']),
      $withContactAccess
    );
    $this->assertEquals([], $withoutContactAccess);
  }

  /**
   * Permissions for one category give no cases of another category.
   */
  public function testOtherCategoriesAreNotReturned() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'access all award and activities',
      'access my cases and activities',
    ]);

    $this->assertEquals(
      $this->getCaseIds(['my_case', 'my_award', 'other_award']),
      $this->getApi4CaseIds()
    );
  }

  /**
   * Without a case permission in any category, the read is still refused.
   */
  public function testReadIsRefusedWithoutCasePermission() {
    $this->setPermissions(['access CiviCRM', 'view all contacts']);

    $this->expectException(UnauthorizedException::class);
    $this->getApi4CaseIds();
  }

  /**
   * Basic information alone gives no API4 case reads, as in core.
   */
  public function testReadIsRefusedWithBasicPermissionOnly() {
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'basic award information',
    ]);

    $this->expectException(UnauthorizedException::class);
    $this->getApi4CaseIds();
  }

  /**
   * Write actions and other case entities stay refused.
   */
  public function testWriteActionsAndOtherEntitiesAreStillRefused() {
    $this->setPermissions(self::MY_AWARD_PERMISSIONS);
    $myAwardId = (int) static::$cases['my_award']['id'];
    $requests = [
      'Case.create' => CiviCase::create(TRUE)->setValues([
        'case_type_id' => static::$caseTypes['award']['id'],
        'contact_id' => static::$client['id'],
        'creator_id' => static::$client['id'],
        'subject' => 'Refused',
      ]),
      'Case.update' => CiviCase::update(TRUE)
        ->addWhere('id', '=', $myAwardId)
        ->addValue('subject', 'Refused'),
      'Case.save' => CiviCase::save(TRUE)->addRecord([
        'id' => $myAwardId,
        'subject' => 'Refused',
      ]),
      'Case.delete' => CiviCase::delete(TRUE)->addWhere('id', '=', $myAwardId),
      'CaseContact.create' => CaseContact::create(TRUE)->setValues([
        'case_id' => $myAwardId,
        'contact_id' => static::$caseManager['id'],
      ]),
      'CaseActivity.get' => CaseActivity::get(TRUE),
    ];

    $allowed = $this->getAllowedRequests($requests);
    $subject = civicrm_api3('Case', 'getvalue', [
      'id' => $myAwardId,
      'return' => 'subject',
    ]);

    $this->assertEquals([], $allowed);
    $this->assertNotEquals('Refused', $subject);
  }

  /**
   * Case queries joined through CaseContact return only role cases.
   */
  public function testCaseQueriesThroughCaseContactReturnOnlyCasesWithCaseRole() {
    $this->setPermissions(self::MY_AWARD_PERMISSIONS);
    $allCaseIds = $this->getCaseIds(self::ALL_CASES);
    $clientLink = ['id', '=', 'client.case_id'];

    $caseClients = CiviCase::get(TRUE)
      ->addSelect('id', 'client.id')
      ->addJoin('Contact AS client', 'INNER', 'CaseContact', $clientLink)
      ->addWhere('id', 'IN', $allCaseIds)
      ->execute()
      ->getArrayCopy();
    $caseContactCaseIds = CaseContact::get(TRUE)
      ->addSelect('case_id')
      ->addWhere('case_id', 'IN', $allCaseIds)
      ->execute()
      ->column('case_id');

    $expectedRow = [
      'id' => (int) static::$cases['my_award']['id'],
      'client.id' => (int) static::$client['id'],
    ];
    $this->assertEquals(
      [$expectedRow],
      array_map(fn($row) => array_map('intval', $row), $caseClients)
    );
    $this->assertEquals(
      $this->getCaseIds(['my_award']),
      array_map('intval', $caseContactCaseIds)
    );
  }

  /**
   * Report shaped case joins keep the case ACL on the joined cases.
   */
  public function testReportShapedCaseJoinsReturnOnlyCasesWithCaseRole() {
    $this->setPermissions(self::MY_AWARD_PERMISSIONS);
    [$restrictedIds, $restrictedJoinedIds] = $this->getReportShapedCaseIds();

    $this->resetPermissions();
    [$allIds, $allJoinedIds] = $this->getReportShapedCaseIds();

    $this->assertEquals($this->getCaseIds(['my_award']), $restrictedIds);
    $this->assertEquals($this->getCaseIds(['my_award']), $restrictedJoinedIds);
    $this->assertEquals($this->getCaseIds(self::ALL_CASES), $allIds);
    $this->assertEquals(
      $this->getCaseIds(['my_case', 'my_award']),
      $allJoinedIds
    );
  }

  /**
   * Case joins from another entity are dropped, as core skips their case ACL.
   */
  public function testCaseJoinFromAnotherEntityIsDropped() {
    $this->setPermissions(self::MY_AWARD_PERMISSIONS);
    $caseLink = ['id', '=', 'client_case.contact_id'];

    $clients = Contact::get(TRUE)
      ->addSelect('id', 'client_case.id', 'client_case.subject')
      ->addJoin('Case AS client_case', 'INNER', 'CaseContact', $caseLink)
      ->addWhere('id', '=', static::$client['id'])
      ->execute()
      ->getArrayCopy();
    $caseGetActions = CiviCase::getActions(TRUE)
      ->addWhere('name', '=', 'get')
      ->execute()
      ->count();

    $this->assertEquals([['id' => (int) static::$client['id']]], $clients);
    $this->assertEquals(0, $caseGetActions);
    $this->assertEquals(
      $this->getCaseIds(['my_award']),
      $this->getApi4CaseIds()
    );
  }

  /**
   * Explicit case joins that core can dedupe are refused.
   */
  public function testDirectCaseJoinsAreRefused() {
    $this->setPermissions(self::MY_AWARD_PERMISSIONS);
    $caseTypeLink = ['other.case_type_id', '=', 'id'];
    $caseClientLink = ['case_client.case_id', '=', 'id'];
    $numericKeyJoin = [
      1 => 'Case AS other',
      2 => 'INNER',
      3 => $caseTypeLink,
    ];
    $requests = [
      'Case' => CiviCase::get(TRUE)
        ->addSelect('id', 'other.id')
        ->addJoin('Case AS other', 'INNER', $caseTypeLink),
      'CaseContact' => CiviCase::get(TRUE)
        ->addSelect('id', 'case_client.contact_id')
        ->addJoin('CaseContact AS case_client', 'INNER', $caseClientLink),
      'numeric keys' => CiviCase::get(TRUE)
        ->addSelect('id', 'other.id')
        ->setJoin([$numericKeyJoin]),
    ];

    $this->assertEquals([], $this->getAllowedRequests($requests));
  }

  /**
   * Bridge case joins linked to a bare main entity field are refused.
   */
  public function testBridgeCaseJoinsWithoutAliasLinkAreRefused() {
    $this->setPermissions(self::MY_AWARD_PERMISSIONS);
    $bareLink = ['leak.contact_id', '=', 'id'];
    $requests = [
      'bare link' => CiviCase::get(TRUE)
        ->addSelect('id', 'leak.id')
        ->addJoin('Case AS leak', 'INNER', 'CaseContact', $bareLink),
      'no link' => CiviCase::get(TRUE)
        ->addSelect('id', 'leak.id')
        ->addJoin('Case AS leak', 'INNER', 'CaseContact'),
    ];

    $this->assertEquals([], $this->getAllowedRequests($requests));
  }

  /**
   * Returns the main and joined case ids of a query shaped like the report.
   *
   * The joined cases come through RelationshipCache, linked to the client.
   *
   * @return array
   *   Sorted case ids, and sorted ids of the joined cases.
   */
  private function getReportShapedCaseIds() {
    $clientLink = ['id', '=', 'client.case_id'];
    $relatedCaseLink = ['client.id', '=', 'rel_case.far_contact_id'];

    $rows = CiviCase::get(TRUE)
      ->addSelect('id', 'rel_case.id')
      ->addJoin('Contact AS client', 'INNER', 'CaseContact', $clientLink)
      ->addJoin('Case AS rel_case', 'LEFT', 'RelationshipCache', $relatedCaseLink)
      ->addWhere('id', 'IN', $this->getCaseIds(self::ALL_CASES))
      ->execute();
    $caseIds = array_unique(array_map('intval', $rows->column('id')));
    $joinedIds = array_filter($rows->column('rel_case.id'));
    $joinedIds = array_unique(array_map('intval', $joinedIds));
    sort($caseIds);
    sort($joinedIds);

    return [$caseIds, $joinedIds];
  }

  /**
   * Returns the names of the requests that were not refused.
   *
   * @param \Civi\Api4\Generic\AbstractAction[] $requests
   *   API4 requests, keyed by name.
   *
   * @return string[]
   *   Names of the requests that ran.
   */
  private function getAllowedRequests(array $requests) {
    $allowed = [];
    foreach ($requests as $name => $request) {
      try {
        $request->execute();
        $allowed[] = $name;
      }
      catch (UnauthorizedException $e) {
        // Refused, as expected.
      }
    }

    return $allowed;
  }

  /**
   * Returns the fabricated cases that API4 returns to the current user.
   *
   * @return int[]
   *   Sorted case ids.
   */
  private function getApi4CaseIds() {
    $ids = CiviCase::get(TRUE)
      ->addSelect('id')
      ->addWhere('id', 'IN', $this->getCaseIds(self::ALL_CASES))
      ->execute()
      ->column('id');
    $ids = array_map('intval', $ids);
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
