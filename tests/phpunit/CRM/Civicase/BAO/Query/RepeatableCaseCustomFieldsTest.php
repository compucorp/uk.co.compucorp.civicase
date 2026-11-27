<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests Find Cases / Advanced Search on repeatable Case custom fields.
 *
 * @group headless
 */
class CRM_Civicase_BAO_Query_RepeatableCaseCustomFieldsTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * The repeatable Case group.
   *
   * @var array
   */
  private $history;

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
   * Creates the group, Cases and records described in the class comment.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->history = $this->createRepeatableCaseGroup('History', [
      'title' => ['is_searchable' => 1],
      'held_on' => [
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'is_searchable' => 1,
        'is_search_range' => 1,
      ],
    ]);
    foreach (['A', 'B', 'C'] as $key) {
      $this->cases[$key] = $this->createCase();
    }
    $this->createRecord($this->history, $this->cases['A'], ['title' => 'alpha', 'held_on' => '2026-01-10']);
    $this->createRecord($this->history, $this->cases['A'], ['title' => 'beta', 'held_on' => '2026-06-10']);
    $this->createRecord($this->history, $this->cases['B'], ['title' => 'alpha', 'held_on' => '2026-06-10']);
  }

  /**
   * Removes the group, Cases and contacts the test created.
   */
  public function tearDown(): void {
    $this->cleanUpRepeatableCaseCustomData();
  }

  /**
   * A date range on a repeatable field filters the Cases.
   *
   * Without the query object core drops the range and returns every Case.
   */
  public function testDateRangeFiltersCases() {
    $dateKey = $this->fieldKey($this->history, 'held_on');

    $this->assertSame([$this->cases['A']], $this->searchCases([
      "{$dateKey}_relative" => 0,
      "{$dateKey}_low" => '2026-01-01',
      "{$dateKey}_high" => '2026-01-31',
    ]));
    $this->assertSame([$this->cases['A'], $this->cases['B']], $this->searchCases([
      "{$dateKey}_relative" => 0,
      "{$dateKey}_low" => '2026-06-01',
      "{$dateKey}_high" => '2026-06-30',
    ]));
  }

  /**
   * Criteria joined by AND must be met by the same record.
   */
  public function testAndCriteriaUseTheSameRecord() {
    $dateKey = $this->fieldKey($this->history, 'held_on');

    // Case A has "alpha" in January and a June record, but not both together.
    $this->assertSame([$this->cases['B']], $this->searchCases([
      $this->fieldKey($this->history, 'title') => 'alpha',
      "{$dateKey}_relative" => 0,
      "{$dateKey}_low" => '2026-06-01',
      "{$dateKey}_high" => '2026-06-30',
    ]));
  }

  /**
   * A Case with several matching records is listed once.
   */
  public function testCaseIsListedOnce() {
    $dateKey = $this->fieldKey($this->history, 'held_on');

    $this->assertSame([$this->cases['A'], $this->cases['B']], $this->searchCases([
      "{$dateKey}_relative" => 0,
      "{$dateKey}_low" => '2026-01-01',
      "{$dateKey}_high" => '2026-12-31',
    ]));
  }

  /**
   * The query object exposes no fields to other field lists (e.g. Profiles).
   */
  public function testGetFieldsIsEmpty() {
    $this->assertSame([], (new CRM_Civicase_BAO_Query_RepeatableCaseCustomFields())->getFields());
  }

  /**
   * Runs a Find Cases search and returns the matching fixture Case ids.
   *
   * @param array $formValues
   *   Submitted search form values.
   *
   * @return int[]
   *   Fixture Case ids found, in id order, duplicates kept.
   */
  private function searchCases(array $formValues): array {
    $formValues = $formValues + ['case_deleted' => 0];
    $params = CRM_Contact_BAO_Query::convertFormValues($formValues);
    $selector = new CRM_Case_Selector_Search($params, CRM_Core_Action::VIEW, NULL, FALSE, FALSE, 'search');
    $rows = $selector->getRows(CRM_Core_Action::VIEW, 0, 0, NULL);

    $ids = array_values(array_intersect(
      array_map('intval', array_column($rows, 'case_id')),
      array_values($this->cases)
    ));
    sort($ids);

    return $ids;
  }

}
