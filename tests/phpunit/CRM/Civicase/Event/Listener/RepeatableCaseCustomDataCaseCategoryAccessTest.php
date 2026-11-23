<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
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
   */
  public function tearDown(): void {
    $this->resetPermissions();
    $this->cleanUpRepeatableCaseCustomData();
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
   * Edit access to the group is still needed.
   */
  public function testEditAccessToTheGroupIsStillNeeded() {
    $caseId = $this->createCategoryCase('award');
    $this->setPermissions(array_diff(self::AWARD_PERMISSIONS, ['access all custom data']));

    $this->assertFalse($this->canWrite('create', ['entity_id' => $caseId]));
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

}
