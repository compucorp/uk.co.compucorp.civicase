<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use CRM_Civicase_Service_RepeatableCaseCustomImporter as Importer;
use PHPUnit\Framework\TestCase;

/**
 * Tests the repeatable Case custom-data importer service (TCOSB-64 / 1.8).
 *
 * Runs against real repeatable Case custom groups. Creating them runs DDL,
 * which would end a test transaction, so this test is not transactional: the
 * fixture is removed in tearDown() (see
 * CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait). Converting CSV option
 * labels to stored values is done by CiviCRM's import parser before a row
 * reaches the importer; that is covered by the Playwright E2E and manual QA
 * of the CSV Import screen.
 *
 * @group headless
 */
class CRM_Civicase_Service_RepeatableCaseCustomImporterTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * Repeatable group with one field of each kind the importer treats apart.
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
      'year' => ['label' => 'Year', 'data_type' => 'Int'],
      'level' => [
        'label' => 'Level',
        'html_type' => 'Select',
        'option_values' => ['bachelor' => 'Bachelor', 'master' => 'Master'],
      ],
      'country' => [
        'label' => 'Country',
        'data_type' => 'Country',
        'html_type' => 'Select',
      ],
      'state' => [
        'label' => 'State',
        'data_type' => 'StateProvince',
        'html_type' => 'Select',
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
   * Each active field of a repeatable Case group is a column, group-labelled.
   */
  public function testMappableFieldsListsRepeatableCaseGroupFields() {
    $expected = [
      $this->fieldKey($this->education, 'title') => 'Education history: Title',
      $this->fieldKey($this->education, 'year') => 'Education history: Year',
      $this->fieldKey($this->education, 'level') => 'Education history: Level',
      $this->fieldKey($this->education, 'country') => 'Education history: Country',
      $this->fieldKey($this->education, 'state') => 'Education history: State',
      $this->fieldKey($this->education, 'is_current') => 'Education history: Current',
      $this->fieldKey($this->employment, 'employer') => 'Employment history: Employer',
    ];

    $columns = Importer::mappableFields();

    $this->assertEquals($expected, array_intersect_key($columns, $expected));
  }

  /**
   * Inactive fields and other kinds of custom data are not columns.
   */
  public function testMappableFieldsExcludesOtherCustomData() {
    $archive = $this->createRepeatableCaseGroup('Archive', [
      'kept' => [],
      'retired' => ['is_active' => 0],
    ]);
    $caseDetails = $this->createCustomGroup([
      'title' => 'Case details',
      'extends' => 'Case',
      'style' => 'Inline',
    ], ['notes' => []]);
    $contactHistory = $this->createCustomGroup([
      'title' => 'Contact history',
      'extends' => 'Individual',
      'is_multiple' => 1,
      'style' => 'Tab with table',
    ], ['event' => []]);

    $columns = Importer::mappableFields();

    $this->assertArrayHasKey($this->fieldKey($archive, 'kept'), $columns);
    $this->assertArrayNotHasKey($this->fieldKey($archive, 'retired'), $columns);
    $this->assertArrayNotHasKey($this->fieldKey($caseDetails, 'notes'), $columns);
    $this->assertArrayNotHasKey($this->fieldKey($contactHistory, 'event'), $columns);
  }

  /**
   * Option, Country and State fields carry the metadata the parser needs.
   *
   * With it, CiviCRM's import parser converts an option label, or a country
   * or state name, to the stored value. Yes/No fields are deliberately left
   * out: the parser would then reject inputs such as "true" that import
   * correctly today.
   */
  public function testOptionFieldMetadataCoversOptionCountryAndStateFields() {
    $metadata = Importer::optionFieldMetadata();

    foreach (['level', 'country', 'state'] as $name) {
      $this->assertEquals([
        'custom_field_id' => $this->education['fields'][$name]['id'],
        'is_multiple' => 1,
        'custom_group_id.name' => $this->education['name'],
      ], $metadata[$this->fieldKey($this->education, $name)] ?? NULL, $name);
    }
    foreach (['title', 'year', 'is_current'] as $name) {
      $this->assertArrayNotHasKey($this->fieldKey($this->education, $name), $metadata, $name);
    }
  }

  /**
   * A row with values for one group creates one record in that group.
   */
  public function testImportRowCreatesRecordInGroup() {
    $written = Importer::importRow([
      'case_id' => $this->caseId,
      $this->fieldKey($this->education, 'title') => 'BSc Biology',
      $this->fieldKey($this->education, 'year') => '2019',
    ]);

    $this->assertSame(1, $written);
    $records = $this->getRecords($this->education, $this->caseId);
    $this->assertCount(1, $records);
    $this->assertEquals('BSc Biology', $records[0]['title']);
    $this->assertEquals(2019, $records[0]['year']);
    $this->assertEmpty($this->getRecords($this->employment, $this->caseId));
  }

  /**
   * A row with values for two groups creates one record in each.
   */
  public function testImportRowCreatesOneRecordPerGroupOnTheRow() {
    $written = Importer::importRow([
      'case_id' => $this->caseId,
      $this->fieldKey($this->education, 'title') => 'BSc Biology',
      $this->fieldKey($this->employment, 'employer') => 'Acme Ltd',
    ]);

    $this->assertSame(2, $written);
    $education = $this->getRecords($this->education, $this->caseId);
    $employment = $this->getRecords($this->employment, $this->caseId);
    $this->assertCount(1, $education);
    $this->assertEquals('BSc Biology', $education[0]['title']);
    $this->assertCount(1, $employment);
    $this->assertEquals('Acme Ltd', $employment[0]['employer']);
  }

  /**
   * Rows for the same Case create separate records, never overwriting.
   */
  public function testRowsForTheSameCaseCreateSeparateRecords() {
    $titleKey = $this->fieldKey($this->education, 'title');

    Importer::importRow(['case_id' => $this->caseId, $titleKey => 'BSc Biology']);
    Importer::importRow(['case_id' => $this->caseId, $titleKey => 'MSc Ecology']);

    $records = $this->getRecords($this->education, $this->caseId);
    $this->assertEquals(
      ['BSc Biology', 'MSc Ecology'],
      array_column($records, 'title')
    );
  }

  /**
   * Blank cells are not written, and an all-blank group gets no record.
   */
  public function testBlankCellsAreNotWrittenOnCreate() {
    $written = Importer::importRow([
      'case_id' => $this->caseId,
      $this->fieldKey($this->education, 'title') => 'BSc Biology',
      $this->fieldKey($this->education, 'year') => '',
      $this->fieldKey($this->employment, 'employer') => '',
    ]);

    $this->assertSame(1, $written);
    $records = $this->getRecords($this->education, $this->caseId);
    $this->assertCount(1, $records);
    $this->assertNull($records[0]['year']);
    $this->assertEmpty($this->getRecords($this->employment, $this->caseId));
  }

  /**
   * Option, Country and Yes/No values arrive converted and are stored as is.
   */
  public function testImportRowStoresConvertedValues() {
    Importer::importRow([
      'case_id' => $this->caseId,
      $this->fieldKey($this->education, 'level') => 'master',
      $this->fieldKey($this->education, 'country') => '1226',
      $this->fieldKey($this->education, 'is_current') => '1',
    ]);

    $record = $this->getRecords($this->education, $this->caseId)[0];
    $this->assertEquals('master', $record['level']);
    $this->assertEquals(1226, $record['country']);
    $this->assertEquals(TRUE, $record['is_current']);
  }

  /**
   * A row with a record id updates that record in place.
   */
  public function testImportRowUpdatesRecordById() {
    $recordId = $this->createRecord($this->education, $this->caseId, [
      'title' => 'BSc Biology',
      'year' => 2019,
    ]);

    $written = Importer::importRow([
      'case_id' => $this->caseId,
      'id' => (string) $recordId,
      $this->fieldKey($this->education, 'title') => 'BSc Biology (Hons)',
    ]);

    $this->assertSame(1, $written);
    $records = $this->getRecords($this->education, $this->caseId);
    $this->assertCount(1, $records);
    $this->assertEquals($recordId, $records[0]['id']);
    $this->assertEquals('BSc Biology (Hons)', $records[0]['title']);
    // A column not on the row is left unchanged.
    $this->assertEquals(2019, $records[0]['year']);
  }

  /**
   * Spaces around a record id in the CSV cell are ignored.
   */
  public function testImportRowTrimsSpacesAroundId() {
    $recordId = $this->createRecord($this->education, $this->caseId, [
      'title' => 'BSc Biology',
    ]);

    Importer::importRow([
      'case_id' => $this->caseId,
      'id' => " $recordId ",
      $this->fieldKey($this->education, 'title') => 'BSc Biology (Hons)',
    ]);

    $records = $this->getRecords($this->education, $this->caseId);
    $this->assertCount(1, $records);
    $this->assertEquals('BSc Biology (Hons)', $records[0]['title']);
  }

  /**
   * A record on another Case is neither updated nor moved to this Case.
   */
  public function testImportRowRejectsIdOfRecordOnAnotherCase() {
    $otherCaseId = $this->createCase();
    $recordId = $this->createRecord($this->education, $otherCaseId, [
      'title' => 'BSc Biology',
    ]);

    try {
      Importer::importRow([
        'case_id' => $this->caseId,
        'id' => (string) $recordId,
        $this->fieldKey($this->education, 'title') => 'Changed',
      ]);
      $this->fail('Expected the row to be rejected');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('No repeatable record', $e->getMessage());
    }

    $this->assertEmpty($this->getRecords($this->education, $this->caseId));
    $otherRecords = $this->getRecords($this->education, $otherCaseId);
    $this->assertEquals('BSc Biology', $otherRecords[0]['title']);
  }

  /**
   * A row whose id matches no record on the Case is rejected.
   */
  public function testImportRowRejectsUnmatchedId() {
    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('No repeatable record 999999 found on Case ' . $this->caseId);

    Importer::importRow([
      'case_id' => $this->caseId,
      'id' => '999999',
      $this->fieldKey($this->education, 'title') => 'BSc Biology',
    ]);
  }

  /**
   * A record id that is not a positive whole number is rejected.
   *
   * @param string $id
   *   The id cell.
   *
   * @dataProvider invalidIdProvider
   */
  public function testImportRowRejectsInvalidId(string $id) {
    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('Invalid record id');

    Importer::importRow([
      'case_id' => $this->caseId,
      'id' => $id,
      $this->fieldKey($this->education, 'title') => 'BSc Biology',
    ]);
  }

  /**
   * Record id cells that are not positive whole numbers.
   *
   * @return array
   *   Data sets of [id].
   */
  public function invalidIdProvider(): array {
    return [
      'zero' => ['0'],
      'negative' => ['-3'],
      'decimal' => ['1.5'],
      'text' => ['abc'],
      'digits then text' => ['7x'],
    ];
  }

  /**
   * A row without a Case id is rejected.
   *
   * @param string|null $caseId
   *   The case_id cell, or NULL when the column is absent.
   *
   * @dataProvider missingCaseIdProvider
   */
  public function testImportRowRequiresCaseId($caseId) {
    $row = [$this->fieldKey($this->education, 'title') => 'BSc Biology'];
    if ($caseId !== NULL) {
      $row['case_id'] = $caseId;
    }

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('case_id is required');

    Importer::importRow($row);
  }

  /**
   * Case id cells that do not identify a Case.
   *
   * @return array
   *   Data sets of [case_id].
   */
  public function missingCaseIdProvider(): array {
    return [
      'absent' => [NULL],
      'blank' => [''],
      'zero' => ['0'],
    ];
  }

  /**
   * A row for a Case that does not exist is rejected.
   */
  public function testImportRowRejectsUnknownCase() {
    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('Case 999999999 not found');

    Importer::importRow([
      'case_id' => 999999999,
      $this->fieldKey($this->education, 'title') => 'BSc Biology',
    ]);
  }

  /**
   * A row whose cells are all blank has nothing to import.
   */
  public function testImportRowRejectsRowWithOnlyBlankValues() {
    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('no repeatable Case custom field values');

    Importer::importRow([
      'case_id' => $this->caseId,
      $this->fieldKey($this->education, 'title') => '',
      $this->fieldKey($this->employment, 'employer') => '',
    ]);
  }

  /**
   * Values for custom data outside repeatable Case groups are not imported.
   */
  public function testImportRowIgnoresColumnsOutsideRepeatableCaseGroups() {
    $caseDetails = $this->createCustomGroup([
      'title' => 'Case details',
      'extends' => 'Case',
      'style' => 'Inline',
    ], ['notes' => []]);

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('no repeatable Case custom field values');

    Importer::importRow([
      'case_id' => $this->caseId,
      $this->fieldKey($caseDetails, 'notes') => 'Some notes',
    ]);
  }

}
