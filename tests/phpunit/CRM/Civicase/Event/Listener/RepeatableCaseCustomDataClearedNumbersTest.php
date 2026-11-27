<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests that blank Money and Float values of repeatable Case groups stay empty.
 *
 * @group headless
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataClearedNumbersTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * Repeatable Case group with a text, a Money and a Float field.
   *
   * @var array
   */
  private $group;

  /**
   * Case the records are on.
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
   * Creates the group and a Case.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->caseId = $this->createCase();
    $this->group = $this->createRepeatableCaseGroup('Grants', [
      'title' => ['label' => 'Title'],
      'amount' => ['label' => 'Amount', 'data_type' => 'Money'],
      'hours' => ['label' => 'Hours', 'data_type' => 'Float'],
    ]);
  }

  /**
   * Removes the group, Cases and contacts the test created.
   */
  public function tearDown(): void {
    $this->cleanUpRepeatableCaseCustomData();
  }

  /**
   * Clearing a value empties it, whether it is sent as NULL or ''.
   *
   * A value of 0 is still 0, and fields not sent are unchanged.
   */
  public function testUpdateEmptiesClearedValues() {
    $nullId = $this->createRecord($this->group, $this->caseId, ['title' => 'A', 'amount' => 10, 'hours' => 1.5]);
    $emptyId = $this->createRecord($this->group, $this->caseId, ['title' => 'B', 'amount' => 20, 'hours' => 2.5]);
    $zeroId = $this->createRecord($this->group, $this->caseId, ['title' => 'C', 'amount' => 30, 'hours' => 3.5]);
    $partId = $this->createRecord($this->group, $this->caseId, ['title' => 'D', 'amount' => 40, 'hours' => 4.5]);

    $this->update($nullId, ['amount' => NULL, 'hours' => NULL]);
    $this->update($emptyId, ['amount' => '', 'hours' => '']);
    $this->update($zeroId, ['amount' => 0, 'hours' => '0']);
    $this->update($partId, ['amount' => '']);

    $this->assertSame([
      $nullId => ['A', NULL, NULL],
      $emptyId => ['B', NULL, NULL],
      $zeroId => ['C', 0.0, 0.0],
      $partId => ['D', NULL, 4.5],
    ], $this->storedValues());
  }

  /**
   * An update matching several records empties the value in each of them.
   */
  public function testUpdateOfSeveralRecordsEmptiesEach() {
    $this->createRecord($this->group, $this->caseId, ['title' => 'A', 'amount' => 10]);
    $this->createRecord($this->group, $this->caseId, ['title' => 'B', 'amount' => 20]);

    civicrm_api4('Custom_' . $this->group['name'], 'update', [
      'values' => ['amount' => ''],
      'where' => [['entity_id', '=', $this->caseId]],
      'checkPermissions' => FALSE,
    ]);

    $this->assertSame([NULL, NULL], array_column($this->storedValues(), 1));
  }

  /**
   * A new record with a blank amount has no amount, not 0.
   */
  public function testCreateLeavesBlankValuesEmpty() {
    $id = (int) civicrm_api4('Custom_' . $this->group['name'], 'create', [
      'values' => ['entity_id' => $this->caseId, 'title' => 'A', 'amount' => '', 'hours' => NULL],
      'checkPermissions' => FALSE,
    ])->first()['id'];

    $this->assertSame([$id => ['A', NULL, NULL]], $this->storedValues());
  }

  /**
   * A save empties each record's own blank values.
   *
   * As the case tab forms save records.
   */
  public function testSaveEmptiesEachRecordsBlankValues() {
    $amountId = $this->createRecord($this->group, $this->caseId, ['title' => 'A', 'amount' => 10, 'hours' => 1.5]);
    $hoursId = $this->createRecord($this->group, $this->caseId, ['title' => 'B', 'amount' => 20, 'hours' => 2.5]);

    $saved = civicrm_api4('Custom_' . $this->group['name'], 'save', [
      'records' => [
        ['id' => $amountId, 'amount' => ''],
        ['id' => $hoursId, 'hours' => NULL],
        ['title' => 'C', 'amount' => '', 'hours' => 3.5],
        ['title' => 'D', 'amount' => 5],
      ],
      'defaults' => ['entity_id' => $this->caseId],
      'checkPermissions' => FALSE,
    ]);
    [, , $newId, $keptId] = array_map('intval', $saved->column('id'));

    $this->assertSame([
      $amountId => ['A', NULL, 1.5],
      $hoursId => ['B', 20.0, NULL],
      $newId => ['C', NULL, 3.5],
      $keptId => ['D', 5.0, NULL],
    ], $this->storedValues());
  }

  /**
   * Updates a record.
   *
   * @param int $id
   *   The record.
   * @param array $values
   *   Values keyed by field name.
   */
  private function update(int $id, array $values): void {
    civicrm_api4('Custom_' . $this->group['name'], 'update', [
      'values' => $values,
      'where' => [['id', '=', $id]],
      'checkPermissions' => FALSE,
    ]);
  }

  /**
   * The stored title, amount and hours of the Case's records.
   *
   * Read from the table, so an empty value is told apart from 0.
   *
   * @return array
   *   [title, amount, hours] keyed by record id; amount and hours are floats,
   *   or NULL when empty.
   */
  private function storedValues(): array {
    $group = CRM_Core_BAO_CustomGroup::getGroup(['id' => $this->group['id']]);
    $columns = array_column($group['fields'], 'column_name', 'name');
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT id, `{$columns['title']}` AS title,
        `{$columns['amount']}` AS amount, `{$columns['amount']}` IS NULL AS amount_is_empty,
        `{$columns['hours']}` AS hours, `{$columns['hours']}` IS NULL AS hours_is_empty
        FROM `{$group['table_name']}` WHERE entity_id = %1 ORDER BY id",
      [1 => [$this->caseId, 'Positive']]
    );
    $values = [];
    while ($dao->fetch()) {
      $values[(int) $dao->id] = [
        $dao->title,
        $dao->amount_is_empty ? NULL : (float) $dao->amount,
        $dao->hours_is_empty ? NULL : (float) $dao->hours,
      ];
    }

    return $values;
  }

}
