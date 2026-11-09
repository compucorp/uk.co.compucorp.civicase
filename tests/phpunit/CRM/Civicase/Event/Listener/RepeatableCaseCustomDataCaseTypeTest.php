<?php

use Civi\Api4\Event\ValidateValuesEvent;
use CRM_Civicase_Event_Listener_RepeatableCaseCustomDataCaseType as CaseTypeCheck;
use CRM_Civicase_Test_Fabricator_Case as CaseFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * Tests that repeatable Case custom records stay on their group's case types.
 *
 * @group headless
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataCaseTypeTest extends BaseHeadlessTest {

  /**
   * A case of the case type the group is for.
   *
   * @var int
   */
  private $allowedCaseId;

  /**
   * A case of another case type.
   *
   * @var int
   */
  private $otherCaseId;

  /**
   * The case type the group is for.
   *
   * @var int
   */
  private $allowedCaseTypeId;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $contact = ContactFabricator::fabricate();
    $allowedType = CaseTypeFabricator::fabricate(['name' => 'tcosb104_allowed', 'title' => 'Allowed']);
    $otherType = CaseTypeFabricator::fabricate(['name' => 'tcosb104_other', 'title' => 'Other']);
    $this->allowedCaseTypeId = (int) $allowedType['id'];
    $caseParams = ['contact_id' => $contact['id'], 'creator_id' => $contact['id']];
    $this->allowedCaseId = (int) CaseFabricator::fabricate($caseParams + ['case_type_id' => $allowedType['id']])['id'];
    $this->otherCaseId = (int) CaseFabricator::fabricate($caseParams + ['case_type_id' => $otherType['id']])['id'];
  }

  /**
   * A record on a case of another type is refused; one on its own type isn't.
   */
  public function testFindsRecordsOnOtherCaseTypes() {
    $records = [
      'own' => ['entity_id' => $this->allowedCaseId, 'field' => 'a'],
      'other' => ['entity_id' => (string) $this->otherCaseId, 'field' => 'b'],
    ];

    $this->assertEquals(['other'], CaseTypeCheck::getRecordsOnOtherCaseTypes($this->limitedGroup(), $records));
  }

  /**
   * A group for every case type accepts records on any case.
   */
  public function testGroupForAllCaseTypesAcceptsAnyCase() {
    $group = ['extends_entity_column_value' => NULL];
    $records = [['entity_id' => $this->allowedCaseId], ['entity_id' => $this->otherCaseId]];

    $this->assertEquals([], CaseTypeCheck::getRecordsOnOtherCaseTypes($group, $records));
  }

  /**
   * Records that don't set the case, or name no real case, are left to core.
   */
  public function testIgnoresRecordsWithoutKnownCase() {
    $records = [
      ['field' => 'only a value'],
      ['entity_id' => ''],
      ['entity_id' => 'abc'],
      ['entity_id' => 999999999],
    ];

    $this->assertEquals([], CaseTypeCheck::getRecordsOnOtherCaseTypes($this->limitedGroup(), $records));
  }

  /**
   * The validate listener leaves entities other than Custom_* alone.
   */
  public function testValidateIgnoresOtherEntities() {
    $event = $this->validateEvent('Contact', [['entity_id' => $this->otherCaseId]]);

    CaseTypeCheck::validateValues($event);

    $this->assertEquals([], $event->errors);
  }

  /**
   * The validate listener leaves custom groups that don't exist alone.
   */
  public function testValidateIgnoresUnknownCustomGroups() {
    $event = $this->validateEvent('Custom_NotACaseGroup', [['entity_id' => $this->otherCaseId]]);

    CaseTypeCheck::validateValues($event);

    $this->assertEquals([], $event->errors);
  }

  /**
   * A group limited to the "allowed" case type, as APIv4 returns it.
   *
   * @return array
   *   CustomGroup-shaped array.
   */
  private function limitedGroup(): array {
    return [
      'name' => 'Publications',
      'title' => 'Publications',
      'extends' => 'Case',
      'is_multiple' => 1,
      'extends_entity_column_value' => [(string) $this->allowedCaseTypeId],
    ];
  }

  /**
   * Builds a validate event for an APIv4 create.
   *
   * @param string $entity
   *   APIv4 entity name.
   * @param array $records
   *   Records being saved.
   *
   * @return \Civi\Api4\Event\ValidateValuesEvent
   *   The event.
   */
  private function validateEvent(string $entity, array $records): ValidateValuesEvent {
    // The event reads the entity through array access, so a plain request
    // array stands in for the APIv4 action.
    $request = ['version' => 4, 'entity' => $entity, 'action' => 'create'];

    return new ValidateValuesEvent($request, $records, []);
  }

}
