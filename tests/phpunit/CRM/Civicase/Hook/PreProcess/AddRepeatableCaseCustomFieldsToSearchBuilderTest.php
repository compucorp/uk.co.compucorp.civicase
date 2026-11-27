<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use CRM_Civicase_Hook_PreProcess_AddRepeatableCaseCustomFieldsToSearchBuilder as AddToSearchBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests repeatable Case custom fields in Search Builder (TCOSB-57).
 *
 * Creating custom groups runs DDL, so this test is not transactional; the
 * fixture is removed in tearDown().
 *
 * @group headless
 */
class CRM_Civicase_Hook_PreProcess_AddRepeatableCaseCustomFieldsToSearchBuilderTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * Key of the repeatable Case field.
   *
   * @var string
   */
  private $repeatableKey;

  /**
   * Key of the non-repeatable Case field.
   *
   * @var string
   */
  private $singleKey;

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
   * Creates a repeatable and a non-repeatable Case group.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $repeatable = $this->createRepeatableCaseGroup('History', ['title' => []]);
    $single = $this->createCustomGroup([
      'title' => 'Details',
      'extends' => 'Case',
      'style' => 'Inline',
    ], ['note' => []]);
    $this->repeatableKey = $this->fieldKey($repeatable, 'title');
    $this->singleKey = $this->fieldKey($single, 'note');
    $this->resetCaseFields();
  }

  /**
   * Removes the fixture and the Case field list built by the test.
   */
  public function tearDown(): void {
    $this->resetCaseFields();
    $this->cleanUpRepeatableCaseCustomData();
  }

  /**
   * Search Builder lists repeatable Case fields next to the other ones.
   */
  public function testAddsRepeatableFieldsForSearchBuilder() {
    $this->runHook(CRM_Contact_Form_Search_Builder::class);

    $fields = CRM_Case_BAO_Case::exportableFields();
    $this->assertArrayHasKey($this->repeatableKey, $fields);
    $this->assertArrayHasKey($this->singleKey, $fields);
    $this->assertArrayHasKey(
      $this->repeatableKey,
      CRM_Contact_Form_Search_Builder::fields(),
      'Search Builder must know the field type for its JS and validation.'
    );
  }

  /**
   * Other forms (Export, Profiles, ...) keep core's list.
   */
  public function testOtherFormsAreUnchanged() {
    $this->runHook(CRM_Export_Form_Select::class);

    $fields = CRM_Case_BAO_Case::exportableFields();
    $this->assertArrayNotHasKey($this->repeatableKey, $fields);
    $this->assertArrayHasKey($this->singleKey, $fields);
  }

  /**
   * Runs the hook for a form.
   *
   * @param string $formName
   *   Form class name.
   */
  private function runHook(string $formName) {
    $form = new CRM_Core_Form();
    (new AddToSearchBuilder())->run($formName, $form);
  }

  /**
   * Clears core's static cache of the Case search fields.
   */
  private function resetCaseFields() {
    CRM_Case_BAO_Case::$_exportableFields = NULL;
  }

}
