<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use CRM_Civicase_Hook_BuildForm_HideCaseGroupsFromCustomDataImport as HideCaseGroups;
use CRM_Civicase_Hook_ValidateForm_RejectCaseGroupsInCustomDataImport as RejectCaseGroups;
use PHPUnit\Framework\TestCase;

/**
 * Tests Case groups are kept out of "Import Multi-value Custom Data".
 *
 * @group headless
 */
class CRM_Civicase_Hook_BuildForm_HideCaseGroupsFromCustomDataImportTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * A repeatable ("Tab with table") Case group.
   *
   * @var array
   */
  private $repeatableCaseGroup;

  /**
   * A multi-record Case group shown as a plain tab.
   *
   * @var array
   */
  private $tabCaseGroup;

  /**
   * A multi-record Contact group.
   *
   * @var array
   */
  private $contactGroup;

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
   * Creates multi-record Case and Contact groups.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->repeatableCaseGroup = $this->createRepeatableCaseGroup('Education history', ['title' => []]);
    $this->tabCaseGroup = $this->createCustomGroup([
      'title' => 'Case visits',
      'extends' => 'Case',
      'is_multiple' => 1,
      'style' => 'Tab',
    ], ['visit' => []]);
    $this->contactGroup = $this->createCustomGroup([
      'title' => 'Contact history',
      'extends' => 'Individual',
      'is_multiple' => 1,
      'style' => 'Tab with table',
    ], ['event' => []]);
  }

  /**
   * Removes the groups and contacts the test created.
   */
  public function tearDown(): void {
    $this->cleanUpRepeatableCaseCustomData();
  }

  /**
   * The group list keeps Contact groups and drops every Case group.
   */
  public function testCaseGroupsAreRemovedFromTheGroupList() {
    $form = $this->buildDataSourceForm();

    (new HideCaseGroups())->run($form, HideCaseGroups::FORM_NAME);

    $this->assertEquals(
      ['', (string) $this->contactGroup['id']],
      $this->getOptionValues($form)
    );
  }

  /**
   * Other forms with the same element name are left alone.
   */
  public function testOtherFormsAreUnchanged() {
    $form = $this->buildDataSourceForm();

    (new HideCaseGroups())->run($form, 'CRM_Contact_Import_Form_DataSource');

    $this->assertCount(4, $this->getOptionValues($form));
  }

  /**
   * A submitted Case group fails validation; a Contact group passes.
   */
  public function testSubmittedCaseGroupIsRejected() {
    $hook = new RejectCaseGroups();
    $files = [];
    $form = NULL;

    foreach ([$this->repeatableCaseGroup, $this->tabCaseGroup] as $group) {
      $fields = [HideCaseGroups::ELEMENT_NAME => (string) $group['id']];
      $errors = [];
      $hook->run(HideCaseGroups::FORM_NAME, $fields, $files, $form, $errors);
      $this->assertArrayHasKey(HideCaseGroups::ELEMENT_NAME, $errors, $group['title']);
    }

    $fields = [HideCaseGroups::ELEMENT_NAME => (string) $this->contactGroup['id']];
    $errors = [];
    $hook->run(HideCaseGroups::FORM_NAME, $fields, $files, $form, $errors);
    $this->assertEmpty($errors);
  }

  /**
   * A form with the group select as core's DataSource screen builds it.
   *
   * @return CRM_Core_Form
   *   The form.
   */
  private function buildDataSourceForm(): CRM_Core_Form {
    $form = new CRM_Core_Form();
    $form->add('select', HideCaseGroups::ELEMENT_NAME, 'Multi-value Custom Data', [
      '' => '- select -',
      $this->repeatableCaseGroup['id'] => $this->repeatableCaseGroup['title'],
      $this->tabCaseGroup['id'] => $this->tabCaseGroup['title'],
      $this->contactGroup['id'] => $this->contactGroup['title'],
    ], TRUE);

    return $form;
  }

  /**
   * The option values of the form's group select.
   *
   * @param CRM_Core_Form $form
   *   The form.
   *
   * @return string[]
   *   Option values, in order.
   */
  private function getOptionValues(CRM_Core_Form $form): array {
    $options = $form->getElement(HideCaseGroups::ELEMENT_NAME)->_options;

    return array_map(fn ($option) => (string) $option['attr']['value'], $options);
  }

}
