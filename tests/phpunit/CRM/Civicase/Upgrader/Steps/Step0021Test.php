<?php

use CRM_Civicase_Upgrader_Steps_Step0021 as Step0021;

/**
 * Tests the Step0021 upgrader (enable repeatable custom field sets for Cases).
 *
 * @group headless
 */
class CRM_Civicase_Upgrader_Steps_Step0021Test extends BaseHeadlessTest {

  /**
   * The upgrader enables multiple records on existing Case CG extends entities.
   */
  public function testEnablesMultipleRecordsOnCaseEntities() {
    $caseCategory = $this->createCgExtendOptionValue('civicrm_case', 0);
    $caseType = $this->createCgExtendOptionValue('civicrm_case_type', 0);

    (new Step0021())->apply();

    $this->assertEquals(1, $this->getFilter($caseCategory['value']));
    $this->assertEquals(1, $this->getFilter($caseType['value']));
  }

  /**
   * The upgrader leaves non-Case CG extends entities untouched.
   */
  public function testDoesNotAffectNonCaseEntities() {
    $survey = $this->createCgExtendOptionValue('civicrm_survey', 0);

    (new Step0021())->apply();

    $this->assertEquals(0, $this->getFilter($survey['value']));
  }

  /**
   * The upgrader is idempotent - running it twice keeps records enabled.
   */
  public function testIsIdempotent() {
    $caseCategory = $this->createCgExtendOptionValue('civicrm_case', 0);

    (new Step0021())->apply();
    (new Step0021())->apply();

    $this->assertEquals(1, $this->getFilter($caseCategory['value']));
  }

  /**
   * Case entities are flagged to allow multiple records, as Contacts are.
   *
   * The `filter` flag on the cg_extend_objects option value is what maps to
   * `allow_is_multiple` and drives the multiple-records controls on the Custom
   * Group form (for Contacts and, after this change, Cases).
   */
  public function testCaseEntitiesAllowMultipleRecords() {
    $caseCategory = $this->createCgExtendOptionValue('civicrm_case', 0);
    $caseType = $this->createCgExtendOptionValue('civicrm_case_type', 0);

    (new Step0021())->apply();

    $this->assertEquals(1, $this->getFilter($caseCategory['value']));
    $this->assertEquals(1, $this->getFilter($caseType['value']));
  }

  /**
   * Returns the filter value of a cg_extend_objects option value.
   *
   * @param string $value
   *   Value of the CG extend option.
   *
   * @return int
   *   The filter value.
   */
  private function getFilter($value) {
    $optionValue = civicrm_api3('OptionValue', 'getsingle', [
      'value' => $value,
      'option_group_id' => 'cg_extend_objects',
    ]);

    return (int) $optionValue['filter'];
  }

  /**
   * Creates a cg_extend_objects option value with the given name and filter.
   *
   * @param string $name
   *   Entity table name (e.g. civicrm_case).
   * @param int $filter
   *   Initial filter value.
   *
   * @return array
   *   CG extend option value details.
   */
  private function createCgExtendOptionValue($name, $filter) {
    $randNumber = rand(0, 100000);
    $result = civicrm_api3('OptionValue', 'create', [
      'option_group_id' => 'cg_extend_objects',
      'name' => $name,
      'label' => 'Test Label ' . $randNumber,
      'value' => 'TestValue' . $randNumber,
      'filter' => $filter,
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    return array_shift($result['values']);
  }

}
