<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests case list filters on repeatable Case custom fields.
 *
 * @group headless
 */
class CRM_Civicase_APIHelpers_CustomFieldFilterRepeatableTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * The repeatable Case group.
   *
   * @var array
   */
  private $history;

  /**
   * A non-repeatable Case group.
   *
   * @var array
   */
  private $details;

  /**
   * Case ids keyed by 'A', 'B' and 'C'.
   *
   * @var int[]
   */
  private $cases = [];

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
   * Creates the groups, Cases and records described in the class comment.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->history = $this->createRepeatableCaseGroup('History', [
      'title' => [],
      'year' => ['data_type' => 'Int'],
      'colour' => [
        'html_type' => 'Select',
        'option_values' => ['red' => 'Red', 'blue' => 'Blue'],
      ],
      'held_on' => ['data_type' => 'Date', 'html_type' => 'Select Date'],
    ]);
    $this->details = $this->createCustomGroup([
      'title' => 'Details',
      'extends' => 'Case',
      'style' => 'Inline',
    ], ['note' => []]);

    foreach (['A', 'B', 'C'] as $key) {
      $this->cases[$key] = $this->createCase();
    }
    $this->createRecord($this->history, $this->cases['A'], [
      'title' => 'alpha',
      'year' => 1,
      'colour' => 'red',
      'held_on' => '2026-01-10',
    ]);
    $this->createRecord($this->history, $this->cases['A'], [
      'title' => 'beta',
      'year' => 2,
      'colour' => 'blue',
      'held_on' => '2026-06-10',
    ]);
    $this->createRecord($this->history, $this->cases['B'], [
      'title' => 'alpha',
      'year' => 2,
      'colour' => 'blue',
      'held_on' => '2026-06-10',
    ]);
    foreach (['A' => 'one', 'B' => 'two', 'C' => 'one'] as $key => $note) {
      civicrm_api3('Case', 'create', [
        'id' => $this->cases[$key],
        $this->fieldKey($this->details, 'note') => $note,
      ]);
    }
  }

  /**
   * Removes the groups, Cases and contacts the test created.
   */
  public function tearDown(): void {
    $this->cleanUpRepeatableCaseCustomData();
  }

  /**
   * A single criterion matches a Case when any of its records matches.
   */
  public function testCaseMatchesWhenAnyRecordMatches() {
    $this->assertMatches(['A'], ['title' => 'beta']);
    $this->assertMatches(['A', 'B'], ['colour' => ['IN' => ['blue']]]);
  }

  /**
   * Criteria on the same group must be met by the same record.
   */
  public function testAllCriteriaMustBeMetBySameRecord() {
    // Case A has "alpha" and year 2, but on different records.
    $this->assertMatches(['B'], ['title' => 'alpha', 'year' => 2]);
    // Mixes an option field with a plain field.
    $this->assertMatches([], ['colour' => ['IN' => ['red']], 'title' => 'beta']);
    $this->assertMatches(['A'], ['colour' => ['IN' => ['red']], 'year' => 1]);
  }

  /**
   * A Case with several matching records is listed and counted once.
   */
  public function testCaseIsListedAndCountedOnce() {
    $filters = ['year' => ['BETWEEN' => [1, 2]]];

    $this->assertMatches(['A', 'B'], $filters);
    $this->assertSame(2, $this->countMatches($filters));
  }

  /**
   * Date ranges are applied to the records.
   */
  public function testDateRange() {
    $this->assertMatches(['A'], ['held_on' => ['BETWEEN' => ['2026-01-01', '2026-01-31 23:59:59']]]);
    $this->assertMatches(['A', 'B'], ['held_on' => ['>=' => '2026-06-01']]);
  }

  /**
   * Every operator of a multi-operator filter is applied to the same record.
   */
  public function testMultipleOperatorsAreAllApplied() {
    $this->assertMatches(['A'], ['held_on' => ['>=' => '2026-01-01', '<=' => '2026-01-31 23:59:59']]);
    $this->assertMatches(['A', 'B'], ['year' => ['>=' => 2, '<=' => 2]]);
    $this->assertMatches(['A'], ['year' => ['>' => 0, '<' => 2]]);
  }

  /**
   * An empty IN list does not filter anything.
   */
  public function testEmptyInListDoesNotFilter() {
    $this->assertMatches(['A', 'B', 'C'], ['title' => ['IN' => []]]);
    $this->assertMatches(['A', 'B', 'C'], ['colour' => ['IN' => []]]);
    $this->assertMatches(['B'], ['title' => 'alpha', 'year' => ['IN' => [2], 'NOT IN' => []]]);
  }

  /**
   * IS NULL matches Cases without any record, like the Case API does.
   */
  public function testIsNullMatchesCasesWithoutRecords() {
    $this->assertMatches(['C'], ['title' => ['IS NULL' => 1]]);
    $this->assertMatches(['A', 'B'], ['title' => ['IS NOT NULL' => 1]]);
  }

  /**
   * Non-repeatable fields keep working, also combined with repeatable ones.
   */
  public function testNonRepeatableFieldsAreUnchanged() {
    $note = $this->fieldKey($this->details, 'note');

    $this->assertMatches(['A', 'C'], [], [$note => 'one']);
    $this->assertMatches(['A'], ['title' => 'alpha'], [$note => 'one']);
  }

  /**
   * A multi-value contact reference matches any of the selected contacts.
   */
  public function testMultiValueContactReference() {
    $group = $this->createRepeatableCaseGroup('Contacts', [
      'people' => [
        'data_type' => 'ContactReference',
        'html_type' => 'Autocomplete-Select',
        'serialize' => 1,
      ],
    ]);
    $first = $this->createContact();
    $second = $this->createContact();
    $this->insertSerializedValue($group, 'people', $this->cases['A'], [$first, $second]);
    $this->insertSerializedValue($group, 'people', $this->cases['B'], [$second]);
    $key = $this->fieldKey($group, 'people');

    $this->assertMatches(['A'], [], [$key => [$first]]);
    $this->assertMatches(['A', 'B'], [], [$key => [$second]]);
    $this->assertMatches(['A', 'B'], [], [$key => [$first, $second]]);
    $this->assertMatches(['A'], [], [$key => $first]);
  }

  /**
   * Values are escaped, so quotes do not break the query.
   */
  public function testValuesAreEscaped() {
    $this->assertMatches([], ['title' => "o'clock"]);
    $this->assertMatches([], ['colour' => ['IN' => ["re'd"]]]);
  }

  /**
   * Asserts which fixture Cases match the given filters.
   *
   * @param string[] $expected
   *   Expected Case keys ('A', 'B', 'C').
   * @param array $filters
   *   Filters on the repeatable group, keyed by field name.
   * @param array $extraParams
   *   Other getcaselist params.
   */
  private function assertMatches(array $expected, array $filters, array $extraParams = []) {
    $result = civicrm_api3('Case', 'getcaselist', $this->buildParams($filters, $extraParams) + [
      'sequential' => 1,
      'return' => ['id'],
      'options' => ['limit' => 0],
    ]);
    $ids = array_map('intval', array_column($result['values'], 'id'));

    $expectedIds = array_map(function ($key) {
      return $this->cases[$key];
    }, $expected);
    sort($ids);
    sort($expectedIds);
    $this->assertSame($expectedIds, $ids);
  }

  /**
   * Stores a record with a multi-value field in its stored format.
   *
   * Written directly, as APIv4 does not accept several contacts for a
   * contact reference field.
   *
   * @param array $group
   *   A group returned by createCustomGroup().
   * @param string $fieldName
   *   Field name within the group.
   * @param int $caseId
   *   The Case the record belongs to.
   * @param array $values
   *   The field values.
   */
  private function insertSerializedValue(array $group, string $fieldName, int $caseId, array $values) {
    CRM_Core_DAO::executeQuery(
      "INSERT INTO `{$group['table_name']}` (entity_id, `{$group['fields'][$fieldName]['column_name']}`) VALUES (%1, %2)",
      [
        1 => [$caseId, 'Integer'],
        2 => [CRM_Utils_Array::implodePadded($values), 'String'],
      ]
    );
  }

  /**
   * Counts the fixture Cases matching the given filters.
   *
   * @param array $filters
   *   Filters on the repeatable group, keyed by field name.
   *
   * @return int
   *   The count returned by getdetailscount.
   */
  private function countMatches(array $filters): int {
    return (int) civicrm_api3('Case', 'getdetailscount', $this->buildParams($filters));
  }

  /**
   * Builds API params limited to the fixture Cases.
   *
   * @param array $filters
   *   Filters on the repeatable group, keyed by field name.
   * @param array $extraParams
   *   Other params.
   *
   * @return array
   *   API params.
   */
  private function buildParams(array $filters, array $extraParams = []): array {
    $params = $extraParams + [
      'id' => ['IN' => array_values($this->cases)],
      'is_deleted' => 0,
    ];
    foreach ($filters as $fieldName => $value) {
      $params[$this->fieldKey($this->history, $fieldName)] = $value;
    }

    return $params;
  }

}
