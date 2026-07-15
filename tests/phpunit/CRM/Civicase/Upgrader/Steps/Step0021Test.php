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
   * Case entities report allow_is_multiple via the same mechanism as Contacts.
   *
   * `allow_is_multiple` is the flag the Custom Group form uses to offer the
   * multiple-records controls (for Contacts and, after this change, Cases).
   */
  public function testCaseEntitiesAllowMultipleRecords() {
    $case = $this->createCgExtendOptionValue('civicrm_case', 0);

    (new Step0021())->apply();
    CRM_Core_PseudoConstant::flush();

    $allowMultiple = array_column(
      CRM_Core_BAO_CustomGroup::getCustomGroupExtendsOptions(),
      'allow_is_multiple',
      'id'
    );
    $this->assertTrue((bool) ($allowMultiple[$case['value']] ?? FALSE));
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
