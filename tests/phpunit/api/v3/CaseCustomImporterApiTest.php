<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the CaseCustomImporter API (TCOSB-64 / 1.8).
 *
 * The endpoint nz.co.fuzion.csvimport calls once per CSV row to import
 * repeatable Case custom data: its field list drives the column mapping, and
 * its create action imports a row or reports why it was rejected.
 *
 * Runs against real repeatable Case custom groups, so it is not
 * transactional (see CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait);
 * the fixture is removed in tearDown().
 *
 * @group headless
 */
class CaseCustomImporterApiTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * Repeatable group with a text, an option and a Yes/No field.
   *
   * @var array
   */
  private $education;

  /**
   * A second repeatable group, for rows that span groups.
   *
   * @var array
   */
  private $employment;

  /**
   * The Case rows are imported into.
   *
   * @var int
   */
  private $caseId;

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
   * Creates a Case to import into and two repeatable Case groups.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->caseId = $this->createCase();
    $this->education = $this->createRepeatableCaseGroup('Education history', [
      'title' => ['label' => 'Title'],
      'level' => [
        'label' => 'Level',
        'html_type' => 'Select',
        'option_values' => ['bachelor' => 'Bachelor', 'master' => 'Master'],
      ],
      'is_current' => [
        'label' => 'Current',
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
      ],
    ]);
    $this->employment = $this->createRepeatableCaseGroup('Employment history', [
      'employer' => ['label' => 'Employer'],
    ]);
  }

  /**
   * Removes the groups, Cases and contacts the test created.
   */
  public function tearDown(): void {
    $this->cleanUpRepeatableCaseCustomData();
  }

  /**
   * The create fields, rebuilt so they include this test's groups.
   *
   * @return array
   *   getfields values, keyed by field name.
   */
  private function getCreateFields(): array {
    return civicrm_api3('CaseCustomImporter', 'getfields', [
      'action' => 'create',
      'cache_clear' => 1,
    ])['values'];
  }

  /**
   * Case ID is a required column and the record id an optional one.
   */
  public function testGetfieldsExposesCaseIdAndMatchKey() {
    $fields = $this->getCreateFields();

    $this->assertEquals(1, $fields['case_id']['api.required']);
    $this->assertArrayHasKey('id', $fields);
    $this->assertEmpty($fields['id']['api.required'] ?? NULL);
  }

  /**
   * Each repeatable field is a text column labelled with its group.
   */
  public function testGetfieldsListsRepeatableFieldsWithGroupLabels() {
    $fields = $this->getCreateFields();

    $columns = [
      $this->fieldKey($this->education, 'title') => 'Education history: Title',
      $this->fieldKey($this->education, 'level') => 'Education history: Level',
      $this->fieldKey($this->education, 'is_current') => 'Education history: Current',
      $this->fieldKey($this->employment, 'employer') => 'Employment history: Employer',
    ];
    foreach ($columns as $key => $title) {
      $this->assertArrayHasKey($key, $fields);
      $this->assertEquals($title, $fields[$key]['title']);
      $this->assertEquals(CRM_Utils_Type::T_STRING, $fields[$key]['type']);
    }
  }

  /**
   * Option fields identify their custom field, so the parser loads options.
   *
   * Without this, CiviCRM's import parser stores an option label from the
   * CSV verbatim instead of converting it to the option value.
   */
  public function testGetfieldsIdentifiesOptionFieldsForTheImportParser() {
    $fields = $this->getCreateFields();

    $level = $fields[$this->fieldKey($this->education, 'level')];
    $this->assertEquals($this->education['fields']['level']['id'], $level['custom_field_id']);
    $this->assertEquals(1, $level['is_multiple']);
    $this->assertEquals($this->education['name'], $level['custom_group_id.name']);
    foreach (['title', 'is_current'] as $name) {
      $field = $fields[$this->fieldKey($this->education, $name)];
      $this->assertArrayNotHasKey('custom_field_id', $field, $name);
    }
  }

  /**
   * The entity resolves over APIv4 too, listing the same columns.
   *
   * The nz.co.fuzion.csvimport extension calls APIv4 getFields on the import
   * entity, so it must exist in APIv4, not only in APIv3.
   */
  public function testApi4GetFieldsListsTheColumns() {
    $fields = civicrm_api4('CaseCustomImporter', 'getFields', [
      'checkPermissions' => FALSE,
    ])->indexBy('name');

    $this->assertArrayHasKey('case_id', $fields);
    $this->assertArrayHasKey('id', $fields);
    $titleKey = $this->fieldKey($this->education, 'title');
    $this->assertEquals('Education history: Title', $fields[$titleKey]['title']);
  }

  /**
   * The entity has a DAO with no unique indices, and no extra columns.
   *
   * With "Allow Updating An Entity Using Unique Fields" ticked, the CSV
   * Import to API extension reads the import entity's DAO indices, and failed
   * when there was no DAO. With no unique indices it finds nothing to match
   * on, so the row imports as if the option were unticked. The DAO has no
   * fields, so the column list still comes from the API spec alone.
   */
  public function testDaoHasNoUniqueIndicesOrExtraColumns() {
    $dao = _civicrm_api3_get_DAO('CaseCustomImporter');

    $this->assertEquals(CRM_Civicase_Service_RepeatableCaseCustomImporterDAO::class, $dao);
    $this->assertSame([], $dao::indices());
    $unexpected = array_filter(
      array_keys($this->getCreateFields()),
      fn ($key) => !in_array($key, ['case_id', 'id'], TRUE) && strpos($key, 'custom_') !== 0
    );
    $this->assertEmpty($unexpected);
  }

  /**
   * Create imports a row and returns the number of records it wrote.
   */
  public function testCreateWritesOneRecordPerGroupOnTheRow() {
    $result = civicrm_api3('CaseCustomImporter', 'create', [
      'case_id' => $this->caseId,
      $this->fieldKey($this->education, 'title') => 'BSc Biology',
      $this->fieldKey($this->employment, 'employer') => 'Acme Ltd',
    ]);

    $this->assertEquals(0, $result['is_error']);
    $this->assertEquals(2, $result['values']);
    $this->assertCount(1, $this->getRecords($this->education, $this->caseId));
    $this->assertCount(1, $this->getRecords($this->employment, $this->caseId));
  }

  /**
   * Create with a record id updates that record instead of adding one.
   */
  public function testCreateUpdatesRecordById() {
    $recordId = $this->createRecord($this->education, $this->caseId, [
      'title' => 'BSc Biology',
    ]);

    civicrm_api3('CaseCustomImporter', 'create', [
      'case_id' => $this->caseId,
      'id' => $recordId,
      $this->fieldKey($this->education, 'title') => 'BSc Biology (Hons)',
    ]);

    $records = $this->getRecords($this->education, $this->caseId);
    $this->assertCount(1, $records);
    $this->assertEquals('BSc Biology (Hons)', $records[0]['title']);
  }

  /**
   * A rejected row reports the importer's reason, shown per row on import.
   */
  public function testCreateReportsWhyTheRowWasRejected() {
    try {
      civicrm_api3('CaseCustomImporter', 'create', [
        'case_id' => $this->caseId,
        'id' => 999999,
        $this->fieldKey($this->education, 'title') => 'BSc Biology',
      ]);
      $this->fail('Expected the row to be rejected');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertEquals(
        'No repeatable record 999999 found on Case ' . $this->caseId,
        $e->getMessage()
      );
    }
    $this->assertEmpty($this->getRecords($this->education, $this->caseId));
  }

  /**
   * A missing case_id is rejected by the API.
   */
  public function testCreateRequiresCaseId() {
    try {
      civicrm_api3('CaseCustomImporter', 'create', [
        $this->fieldKey($this->education, 'title') => 'BSc Biology',
      ]);
      $this->fail('Expected an API error when case_id is missing');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('case_id', $e->getMessage());
    }
  }

  /**
   * A Case that does not exist is rejected by the API.
   */
  public function testCreateRejectsUnknownCase() {
    try {
      civicrm_api3('CaseCustomImporter', 'create', [
        'case_id' => 999999999,
        $this->fieldKey($this->education, 'title') => 'BSc Biology',
      ]);
      $this->fail('Expected an API error for an unknown Case');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertEquals('Case 999999999 not found', $e->getMessage());
    }
  }

  /**
   * A row with nothing to import is rejected by the API.
   */
  public function testCreateRejectsRowWithNoValues() {
    try {
      civicrm_api3('CaseCustomImporter', 'create', [
        'case_id' => $this->caseId,
        $this->fieldKey($this->education, 'title') => '',
      ]);
      $this->fail('Expected an API error for a row with no values');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('no repeatable Case custom field values', $e->getMessage());
    }
  }

}
