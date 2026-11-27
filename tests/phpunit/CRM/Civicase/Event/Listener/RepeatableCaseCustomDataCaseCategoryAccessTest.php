<?php

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\CustomValue;
use Civi\Api4\Event\AuthorizeRecordEvent;
use Civi\Test;
use Civi\Test\HeadlessInterface;
use CRM_Civicase_Event_Listener_RepeatableCaseCustomDataCaseCategoryAccess as CaseCategoryAccess;
use CRM_Civicase_Test_Fabricator_Relationship as RelationshipFabricator;
use CRM_Civicase_Test_Fabricator_RelationshipType as RelationshipTypeFabricator;
use PHPUnit\Framework\TestCase;

/**
 * Tests case category users writing the records of repeatable Case groups.
 *
 * @group headless
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataCaseCategoryAccessTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * Permissions of a user who can work on every award case.
   */
  const AWARD_PERMISSIONS = [
    'access CiviCRM',
    'view all contacts',
    'access all custom data',
    'access all award and activities',
  ];

  /**
   * The repeatable Case group the records belong to.
   *
   * @var array
   */
  private $group;

  /**
   * The relationship type a test created for a case role, if any.
   *
   * @var int|null
   */
  private $relationshipTypeId;

  /**
   * {@inheritdoc}
   */
  public function setUpHeadless() {
    return Test::headless()
      ->install(['org.civicrm.search_kit'])
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * Logs in a contact, who creates the Cases, and creates the group.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->group = $this->createRepeatableCaseGroup('Grants', ['title' => []]);
  }

  /**
   * Removes the group, Cases, case types and contacts the test created.
   *
   * Deleting the Cases also deletes their case roles, so the relationship
   * type goes last.
   */
  public function tearDown(): void {
    $this->resetPermissions();
    $this->cleanUpRepeatableCaseCustomData();
    if ($this->relationshipTypeId) {
      civicrm_api3('RelationshipType', 'delete', ['id' => $this->relationshipTypeId]);
      $this->relationshipTypeId = NULL;
    }
  }

  /**
   * A user with "access all" for the category adds, edits and deletes records.
   */
  public function testCategoryUserCanAddEditAndDeleteRecords() {
    $caseId = $this->createCategoryCase('award');
    $recordId = $this->createRecord($this->group, $caseId, ['title' => 'First']);
    $this->setPermissions(self::AWARD_PERMISSIONS);

    $this->assertTrue($this->canWrite('create', ['entity_id' => $caseId]));
    $this->assertTrue($this->canWrite('update', ['id' => $recordId]));
    $this->assertTrue($this->canWrite('delete', ['id' => $recordId]));

    $entity = 'Custom_' . $this->group['name'];
    $addedId = (int) civicrm_api4($entity, 'create', [
      'values' => ['entity_id' => $caseId, 'title' => 'Second'],
    ])->first()['id'];
    civicrm_api4($entity, 'update', [
      'values' => ['id' => $recordId, 'title' => 'Changed'],
    ]);
    civicrm_api4($entity, 'delete', ['where' => [['id', '=', $addedId]]]);

    $records = $this->getRecords($this->group, $caseId);
    $this->assertEquals([[$recordId, 'Changed']], array_map(fn ($record) => [$record['id'], $record['title']], $records));
  }

  /**
   * Cases of a category the user has no case permission for are refused.
   */
  public function testCasesOfOtherCategoriesAreRefused() {
    $caseId = $this->createCase();
    $recordId = $this->createRecord($this->group, $caseId, ['title' => 'First']);
    $this->setPermissions(self::AWARD_PERMISSIONS);

    $this->assertFalse($this->canWrite('create', ['entity_id' => $caseId]));
    $this->assertFalse($this->canWrite('update', ['id' => $recordId]));
    $this->assertFalse($this->canWrite('delete', ['id' => $recordId]));

    $this->assertCreateRefused(['entity_id' => $caseId, 'title' => 'Second']);
    $this->assertCount(1, $this->getRecords($this->group, $caseId));
  }

  /**
   * A record naming another Case in `entityID`, which core writes, is refused.
   *
   * Core's writer takes the Case from `entityID` over `entity_id`, so checking
   * only `entity_id` would let a record onto a Case the user cannot work on.
   */
  public function testRecordNamingAnotherCaseAsEntityIdIsRefused() {
    $caseId = $this->createCategoryCase('award');
    $otherCaseId = $this->createCase();
    $recordId = $this->createRecord($this->group, $caseId, ['title' => 'First']);
    $this->setPermissions(self::AWARD_PERMISSIONS);

    $this->assertTrue($this->canWrite('create', ['entity_id' => $caseId, 'entityID' => $caseId]));
    $this->assertFalse($this->canWrite('create', ['entity_id' => $caseId, 'entityID' => $otherCaseId]));
    $this->assertFalse($this->canWrite('update', ['id' => $recordId, 'entityID' => $otherCaseId]));

    $this->assertCreateRefused(['entity_id' => $caseId, 'entityID' => $otherCaseId, 'title' => 'Second']);
    $this->assertEquals([], $this->getRecords($this->group, $otherCaseId));
    $this->assertCount(1, $this->getRecords($this->group, $caseId));
  }

  /**
   * A record cannot be moved to another Case, even one the user works on.
   */
  public function testRecordCannotMoveToAnotherCase() {
    $caseId = $this->createCategoryCase('award');
    $otherCaseId = $this->createCategoryCase('award');
    $recordId = $this->createRecord($this->group, $caseId, ['title' => 'First']);
    $this->setPermissions(self::AWARD_PERMISSIONS);

    $this->assertFalse($this->canWrite('update', ['id' => $recordId, 'entity_id' => $otherCaseId]));
    $this->assertTrue($this->canWrite('update', ['id' => $recordId, 'entity_id' => $caseId]));
  }

  /**
   * Basic case information shows the Cases, but does not allow writes.
   */
  public function testBasicCategoryPermissionDoesNotAllowWrites() {
    $caseId = $this->createCategoryCase('award');
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'access all custom data',
      'basic award information',
    ]);

    $this->assertFalse($this->canWrite('create', ['entity_id' => $caseId]));
  }

  /**
   * A "my" permission for the category needs a role on the Case.
   */
  public function testMyCategoryPermissionNeedsCaseRole() {
    $caseId = $this->createCategoryCase('award');
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'access all custom data',
      'access my award and activities',
    ]);

    $this->assertFalse($this->canWrite('create', ['entity_id' => $caseId]));
  }

  /**
   * A "my" permission for the category and a role on the Case allow writes.
   */
  public function testMyCategoryPermissionWithCaseRoleCanWrite() {
    $caseId = $this->createCategoryCase('award');
    $this->giveLoggedInContactRoleOnCase($caseId);
    $recordId = $this->createRecord($this->group, $caseId, ['title' => 'First']);
    $this->setPermissions([
      'access CiviCRM',
      'view all contacts',
      'access all custom data',
      'access my award and activities',
    ]);

    $this->assertTrue($this->canWrite('create', ['entity_id' => $caseId]));
    $this->assertTrue($this->canWrite('update', ['id' => $recordId]));
    $this->assertTrue($this->canWrite('delete', ['id' => $recordId]));
  }

  /**
   * Edit access to the group is still needed.
   */
  public function testEditAccessToTheGroupIsStillNeeded() {
    $caseId = $this->createCategoryCase('award');
    $this->setPermissions(array_diff(self::AWARD_PERMISSIONS, ['access all custom data']));

    $this->assertFalse($this->canWrite('create', ['entity_id' => $caseId]));
  }

  /**
   * The listener itself does not grant writes without edit access to the group.
   *
   * The API refuses such users before the listener runs, so it is called
   * directly here, on a write core has refused.
   */
  public function testListenerNeedsEditAccessToTheGroup() {
    $caseId = $this->createCategoryCase('award');

    $this->setPermissions(array_diff(self::AWARD_PERMISSIONS, ['access all custom data']));
    $this->assertFalse($this->authorizeRefusedCreate(['entity_id' => $caseId]));

    $this->setPermissions(self::AWARD_PERMISSIONS);
    $this->assertTrue($this->authorizeRefusedCreate(['entity_id' => $caseId]));
  }

  /**
   * Whether the logged-in user may write a record of the group.
   *
   * @param string $action
   *   The APIv4 write action.
   * @param array $values
   *   The record, as written.
   *
   * @return bool
   *   The access APIv4 reports, as the case tab asks for it.
   */
  private function canWrite(string $action, array $values): bool {
    return (bool) civicrm_api4('Custom_' . $this->group['name'], 'checkAccess', [
      'action' => $action,
      'values' => $values,
    ])->first()['access'];
  }

  /**
   * Asserts the logged-in user cannot create a record of the group.
   *
   * @param array $values
   *   The record, as written.
   */
  private function assertCreateRefused(array $values): void {
    try {
      civicrm_api4('Custom_' . $this->group['name'], 'create', ['values' => $values]);
    }
    catch (UnauthorizedException $e) {
      return;
    }

    $this->fail('The record was created.');
  }

  /**
   * Whether the listener grants a create that core has refused.
   *
   * @param array $record
   *   The record, as written.
   *
   * @return bool
   *   The authorization after the listener ran.
   */
  private function authorizeRefusedCreate(array $record): bool {
    $event = new AuthorizeRecordEvent(
      CustomValue::create($this->group['name']),
      $record,
      (int) CRM_Core_Session::getLoggedInContactID()
    );
    $event->setAuthorized(FALSE);
    CaseCategoryAccess::authorizeRecord($event);

    return (bool) $event->isAuthorized();
  }

  /**
   * Gives the logged-in contact a role on a Case, for "my" permissions.
   *
   * @param int $caseId
   *   The Case.
   */
  private function giveLoggedInContactRoleOnCase(int $caseId): void {
    $clientId = (int) CRM_Core_DAO::singleValueQuery(
      'SELECT contact_id FROM civicrm_case_contact WHERE case_id = %1',
      [1 => [$caseId, 'Integer']]
    );
    $suffix = substr(md5((string) mt_rand()), 0, 8);
    $relationshipType = RelationshipTypeFabricator::fabricate([
      'name_a_b' => 'Records manager is ' . $suffix,
      'name_b_a' => 'Records manager of ' . $suffix,
    ]);
    $this->relationshipTypeId = (int) $relationshipType['id'];
    RelationshipFabricator::fabricate([
      'contact_id_a' => $clientId,
      'contact_id_b' => (int) CRM_Core_Session::getLoggedInContactID(),
      'relationship_type_id' => $this->relationshipTypeId,
      'case_id' => $caseId,
    ]);
  }

}
