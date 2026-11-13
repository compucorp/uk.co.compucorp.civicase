<?php

use Civi\Core\Event\GenericHookEvent;
use Civi\Test;
use Civi\Test\HeadlessInterface;
use CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAfform as AfformSupport;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;
use PHPUnit\Framework\TestCase;

/**
 * Tests Form Builder support for repeatable Case custom groups.
 *
 * @group headless
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAfformTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * Name of the test form.
   */
  const FORM = 'afformCivicaseTestRepeatableCase';

  /**
   * Repeatable Case group with a required, a number and an options field.
   *
   * @var array
   */
  private $group;

  /**
   * Client of the cases the form creates.
   *
   * @var int
   */
  private $clientId;

  /**
   * Case type of the cases the form creates.
   *
   * @var int
   */
  private $caseTypeId;

  /**
   * Security of the form's Case entity: RBAC or FBAC.
   *
   * @var string
   */
  private $security = 'RBAC';

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
   * Creates the group, a client and a case type, and provides the form.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->clientId = $this->createContact();
    $this->caseTypeId = (int) CaseTypeFabricator::fabricate()['id'];
    $this->group = $this->createRepeatableCaseGroup('Publications', [
      'title' => ['label' => 'Title', 'is_required' => 1],
      'pages' => ['label' => 'Pages', 'data_type' => 'Int'],
      'kind' => [
        'label' => 'Kind',
        'html_type' => 'Select',
        'option_values' => ['book' => 'Book', 'paper' => 'Paper'],
      ],
    ]);
    Civi::dispatcher()->addListener('civi.afform.get', [$this, 'provideForm']);
  }

  /**
   * Removes the fixture and the form.
   */
  public function tearDown(): void {
    Civi::dispatcher()->removeListener('civi.afform.get', [$this, 'provideForm']);
    $this->resetPermissions();
    $this->cleanUpRepeatableCaseCustomData();
  }

  /**
   * Listener for `civi.afform.get`: a Case form with the group's block.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   The afform.get event.
   */
  public function provideForm($event): void {
    $block = _afform_angular_module_name('afblockCustom_' . $this->group['name'], 'dash');
    $event->afforms[self::FORM] = [
      'name' => self::FORM,
      'type' => 'form',
      'title' => 'Test Case form',
      'permission' => ['access CiviCRM'],
      'layout' => sprintf(
        '<af-form ctrl="afform">'
        . '<af-entity data="{contact_id: %d, case_type_id: %d}" type="Case" name="Case1" label="Case 1" actions="{create: true, update: true}" security="%s" url-autofill="1" />'
        . '<fieldset af-fieldset="Case1">'
        . '<af-field name="subject" />'
        . '<div af-join="Custom_%s" af-repeat="Add" min="1" max="2"><%s></%s></div>'
        . '</fieldset>'
        . '<button ng-click="afform.submit()">Submit</button>'
        . '</af-form>',
        $this->clientId,
        $this->caseTypeId,
        $this->security,
        $this->group['name'],
        $block,
        $block
      ),
    ];
  }

  /**
   * The group's block marks its required fields, and only those, required.
   */
  public function testBlockMarksRequiredFields() {
    $block = civicrm_api4('Afform', 'get', [
      'checkPermissions' => FALSE,
      'select' => ['layout'],
      'layoutFormat' => 'html',
      'where' => [['name', '=', 'afblockCustom_' . $this->group['name']]],
    ])->first();
    $this->assertNotNull($block, 'The group has no field block.');
    $layout = $block['layout'];

    $this->assertStringContainsString('<af-field name="title" defn="{required: true}" />', $layout);
    $this->assertStringContainsString('<af-field name="pages" />', $layout);
    $this->assertStringContainsString('<af-field name="kind" />', $layout);
  }

  /**
   * The browser is told which entities are repeatable Case groups.
   */
  public function testAngularSettingsListRepeatableCaseGroups() {
    $entities = AfformSupport::getAngularSettings()['repeatableCaseEntities'];

    $this->assertContains('Custom_' . $this->group['name'], $entities);
  }

  /**
   * Afform pages load civicase's per-field error messages, once.
   */
  public function testAfformLoadsFormMessagesModule() {
    $modules = [
      'af' => ['requires' => ['crmUtil']],
      AfformSupport::FORM_MODULE => ['requires' => []],
    ];
    $event = GenericHookEvent::create(['angularModules' => &$modules]);

    AfformSupport::addFormMessagesToAfform($event);
    AfformSupport::addFormMessagesToAfform($event);

    $this->assertEquals(['crmUtil', AfformSupport::FORM_MODULE], $modules['af']['requires']);
  }

  /**
   * Only plain af-field tags of the given fields are marked required.
   */
  public function testAddRequiredToLayoutLeavesOtherTagsAlone() {
    $layout = "<af-field name=\"a\" />\n<af-field name=\"b\" />\n<af-field name=\"a\" defn=\"{label: 'A'}\" />";

    $result = AfformSupport::addRequiredToLayout($layout, ['a']);

    $this->assertEquals(
      "<af-field name=\"a\" defn=\"{required: true}\" />\n<af-field name=\"b\" />\n<af-field name=\"a\" defn=\"{label: 'A'}\" />",
      $result
    );
  }

  /**
   * Each entry is saved as a record of the new case.
   */
  public function testCreateSavesEachEntry() {
    $caseId = $this->submitNewCase([
      ['title' => 'First', 'pages' => 10, 'kind' => 'book'],
      ['title' => 'Second'],
    ]);

    $this->assertEquals(
      [['First', 10, 'book'], ['Second', NULL, NULL]],
      $this->recordValues($caseId)
    );
  }

  /**
   * A required field left empty in an entry rejects the whole submission.
   */
  public function testMissingRequiredFieldRejectsSubmission() {
    $error = $this->submitExpectingError([['title' => 'First'], ['pages' => 3]]);

    $this->assertStringContainsString('Title is a required field.', $error);
    $this->assertNoCaseCreated();
  }

  /**
   * A value of the wrong type rejects the submission, naming the entry.
   */
  public function testInvalidNumberRejectsSubmission() {
    $error = $this->submitExpectingError([['title' => 'First', 'pages' => 2], ['title' => 'Second', 'pages' => 'abc']]);

    $this->assertStringContainsString('Pages (entry 2) must be a whole number.', $error);
    $this->assertNoCaseCreated();
  }

  /**
   * A value that is not one of the field's options rejects the submission.
   */
  public function testInvalidOptionRejectsSubmission() {
    $error = $this->submitExpectingError([['title' => 'First', 'kind' => 'video']]);

    $this->assertStringContainsString('Kind has an invalid option.', $error);
    $this->assertNoCaseCreated();
  }

  /**
   * Updating a case keeps records not on the form or removed from it.
   *
   * The form loads only 2 of the case's 3 records (its repeat maximum); the
   * user edits one, removes the other and adds a new entry.
   */
  public function testUpdateNeverDeletesRecords() {
    $caseId = $this->createCase();
    $first = $this->createRecord($this->group, $caseId, ['title' => 'First']);
    $this->createRecord($this->group, $caseId, ['title' => 'Second']);
    $this->createRecord($this->group, $caseId, ['title' => 'Third']);

    $this->submit(
      [['id' => $first, 'title' => 'First (edited)'], ['title' => 'Fourth']],
      ['Case1' => $caseId]
    );

    $this->assertEquals(
      ['First (edited)', 'Second', 'Third', 'Fourth'],
      array_column($this->getRecords($this->group, $caseId), 'title')
    );
  }

  /**
   * An entry whose only value is 0 is saved; a blank one is skipped.
   */
  public function testEntryWithOnlyZeroIsSaved() {
    $caseId = $this->createCase();

    $saved = AfformSupport::saveCaseEntries('Custom_' . $this->group['name'], $caseId, [
      ['pages' => 0],
      ['pages' => '', 'kind' => ['']],
    ]);

    $this->assertEquals([0], array_keys($saved));
    $this->assertEquals([[NULL, 0, NULL]], $this->recordValues($caseId));
  }

  /**
   * An entry naming another case's record creates a record instead.
   */
  public function testEntryForAnotherCasesRecordCreatesNewRecord() {
    $otherCaseId = $this->createCase();
    $otherRecord = $this->createRecord($this->group, $otherCaseId, ['title' => 'Other']);

    $caseId = $this->submitNewCase([['id' => $otherRecord, 'title' => 'Hijacked']]);

    $this->assertEquals(['Other'], array_column($this->getRecords($this->group, $otherCaseId), 'title'));
    $this->assertEquals([['Hijacked', NULL, NULL]], $this->recordValues($caseId));
  }

  /**
   * A user who may not edit the group cannot add records through a form.
   */
  public function testUserWithoutEditAccessIsRejected() {
    $this->setPermissions(['access CiviCRM', 'add cases', 'access all cases and activities']);

    $error = $this->submitExpectingError([['title' => 'First']]);

    $this->assertStringContainsString('You do not have permission to edit Publications records.', $error);
    $this->assertNoCaseCreated();
  }

  /**
   * With form-based security the form's users may add records.
   */
  public function testFormBasedSecurityAllowsUserWithoutEditAccess() {
    $this->security = 'FBAC';
    $this->setPermissions(['access CiviCRM']);

    $caseId = $this->submitNewCase([['title' => 'First']]);

    $this->assertEquals([['First', NULL, NULL]], $this->recordValues($caseId));
  }

  /**
   * Entries matching the stored records do not count as changes.
   */
  public function testUnchangedEntriesAreNotChanges() {
    $caseId = $this->createCase();
    $id = $this->createRecord($this->group, $caseId, ['title' => 'First', 'pages' => 5]);
    $entries = [
      'unchanged' => ['id' => $id, 'title' => 'First', 'pages' => '5', 'kind' => ''],
      'edited' => ['id' => $id, 'title' => 'First (edited)'],
      'new' => ['title' => 'New'],
    ];

    $changes = AfformSupport::getEntriesThatChangeRecords($this->group, $entries, $caseId);

    $this->assertEquals(['edited', 'new'], array_keys($changes));
  }

  /**
   * A required field added to a form on its own is still required.
   *
   * Unless the admin has set it as not required on the form, or the form
   * marks it required (afform core checks those).
   */
  public function testRequiredFieldIsCheckedWhenNotMarkedOnForm() {
    $entry = ['pages' => 3];

    $this->assertEquals(
      ['Publications: Title (entry 2) is a required field.'],
      AfformSupport::getEntryErrors($this->group, $entry, ['title' => [], 'pages' => []], 2)
    );
    $this->assertEquals([], AfformSupport::getEntryErrors($this->group, $entry, ['title' => ['defn' => ['required' => FALSE]]]));
    $this->assertEquals([], AfformSupport::getEntryErrors($this->group, $entry, ['title' => ['defn' => ['required' => TRUE]]]));
    $this->assertEquals([], AfformSupport::getEntryErrors($this->group, $entry, ['pages' => []]));
  }

  /**
   * Values are checked against the field's type and options.
   */
  public function testGetValueError() {
    $field = fn (string $dataType, $options = FALSE, $maxLength = NULL, $serialize = 0) => [
      'data_type' => $dataType,
      'input_type' => 'Text',
      'fk_entity' => NULL,
      'serialize' => $serialize,
      'options' => $options,
      'max_length' => $maxLength,
    ];
    $cases = [
      [$field('Integer'), '12', NULL],
      [$field('Integer'), 'abc', 'X must be a whole number.'],
      [$field('Float'), '1.5', NULL],
      [$field('Money'), 'ten', 'X must be a number.'],
      [$field('Boolean'), FALSE, NULL],
      [$field('Boolean'), 'maybe', 'X must be Yes or No.'],
      [$field('Date'), '2026-01-31', NULL],
      [$field('Date'), 'not a date', 'X must be a valid date.'],
      [$field('String', FALSE, 3), 'abcd', 'X must be 3 characters or fewer.'],
      [$field('Url', FALSE, 255), 'https://example.org/page', NULL],
      [$field('Url', FALSE, 255), 'https:example.org', 'X must be a full web address, such as https://example.org.'],
      [$field('String', ['a' => 'A']), 'a', NULL],
      [$field('String', ['a' => 'A']), 'b', 'X has an invalid option.'],
      [$field('String', ['a' => 'A', 'b' => 'B'], NULL, 1), ['a', 'b'], NULL],
      [$field('String', ['a' => 'A', 'b' => 'B']), ['a', 'b'], 'X accepts only one value.'],
      [$field('Integer'), '', NULL],
    ];
    foreach ($cases as [$definition, $value, $expected]) {
      $this->assertSame($expected, AfformSupport::getValueError($definition, $value, 'X'), json_encode($value));
    }
  }

  /**
   * Submits the form for a new case and returns the case's id.
   *
   * @param array $entries
   *   The group's entries.
   *
   * @return int
   *   The new case's id.
   */
  private function submitNewCase(array $entries): int {
    $result = $this->submit($entries);
    $caseId = (int) ($result['Case1'][0]['id'] ?? 0);
    $this->assertGreaterThan(0, $caseId, 'The case was not created.');
    $this->createdCaseIds[] = $caseId;

    return $caseId;
  }

  /**
   * Submits the form.
   *
   * @param array $entries
   *   The group's entries.
   * @param array $args
   *   The form's arguments, e.g. the case to update.
   *
   * @return array
   *   The ids saved, by form entity.
   */
  private function submit(array $entries, array $args = []): array {
    return (array) civicrm_api4('Afform', 'submit', [
      'name' => self::FORM,
      'args' => $args,
      'values' => [
        'Case1' => [
          [
            'fields' => ['subject' => 'TCOSB-60 test'],
            'joins' => ['Custom_' . $this->group['name'] => $entries],
          ],
        ],
      ],
    ])->first();
  }

  /**
   * Submits the form, expecting it to be rejected.
   *
   * @param array $entries
   *   The group's entries.
   *
   * @return string
   *   The error message.
   */
  private function submitExpectingError(array $entries): string {
    try {
      $this->submit($entries);
    }
    catch (CRM_Core_Exception $e) {
      return $e->getMessage();
    }
    $this->fail('The submission was not rejected.');
  }

  /**
   * Asserts that the form created no case.
   */
  private function assertNoCaseCreated(): void {
    $count = civicrm_api4('Case', 'get', [
      'checkPermissions' => FALSE,
      'where' => [['subject', '=', 'TCOSB-60 test']],
    ])->count();
    $this->assertEquals(0, $count);
  }

  /**
   * The title, pages and kind of a case's records, in id order.
   *
   * @param int $caseId
   *   Case id.
   *
   * @return array
   *   One [title, pages, kind] per record.
   */
  private function recordValues(int $caseId): array {
    return array_map(
      fn ($record) => [$record['title'], $record['pages'], $record['kind']],
      $this->getRecords($this->group, $caseId)
    );
  }

}
