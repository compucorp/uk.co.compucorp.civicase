<?php

use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * Tests the core case form when it is used to delete or restore a case.
 *
 * @group headless
 */
class CRM_Civicase_Form_CaseDeleteAndRestoreTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_FormHookTestTrait;
  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * The request method before the test ran.
   *
   * @var string|null
   */
  private $originalRequestMethod;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    // The form controller only generates a form key for GET requests.
    $this->originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? NULL;
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
  }

  /**
   * {@inheritDoc}
   */
  public function tearDown(): void {
    $this->unregisterCurrentLoggedInContactFromSession();

    if ($this->originalRequestMethod === NULL) {
      unset($_SERVER['REQUEST_METHOD']);
    }
    else {
      $_SERVER['REQUEST_METHOD'] = $this->originalRequestMethod;
    }
  }

  /**
   * Tests the delete and restore forms build for the default "Cases" category.
   *
   * @param int $action
   *   The form action.
   *
   * @dataProvider provideDeleteAndRestoreActions
   */
  public function testFormBuildsForDefaultCaseCategory($action) {
    $form = $this->buildCaseForm($action, CRM_Civicase_Helper_CaseCategory::getOptionValue());

    $this->assertDeleteOrRestoreFormIsBuilt($form);
  }

  /**
   * Tests the delete and restore forms build for a non default case category.
   *
   * @param int $action
   *   The form action.
   *
   * @dataProvider provideDeleteAndRestoreActions
   */
  public function testFormBuildsForNonDefaultCaseCategory($action) {
    $caseCategory = CaseCategoryFabricator::fabricate();
    CRM_Core_PseudoConstant::flush();

    $form = $this->buildCaseForm($action, $caseCategory['value']);

    $this->assertDeleteOrRestoreFormIsBuilt($form);
  }

  /**
   * Asserts the case form was built as a delete or restore form.
   *
   * The form must have the delete/restore ("next") button, which core adds
   * inside the `buttons` group, and no case fields.
   *
   * @param CRM_Case_Form_Case $form
   *   The built form.
   */
  private function assertDeleteOrRestoreFormIsBuilt(CRM_Case_Form_Case $form) {
    $this->assertTrue($form->elementExists('buttons'));
    $buttonNames = array_map(function ($button) {
      return $button->getName();
    }, $form->getElement('buttons')->getElements());
    $this->assertContains($form->getButtonName('next'), $buttonNames);

    $this->assertFalse($form->elementExists('case_type_id'));
  }

  /**
   * Builds the case form for the given action and case category.
   *
   * Calling `buildForm` runs the form's preProcess, the preProcess hooks,
   * buildQuickForm and the buildForm hooks.
   *
   * @param int $action
   *   The form action.
   * @param string|int $caseCategoryId
   *   The case category ID in the form's entry URL.
   *
   * @return CRM_Case_Form_Case
   *   The built form.
   */
  private function buildCaseForm($action, $caseCategoryId) {
    $form = new CRM_Case_Form_Case(NULL, $action);
    $form->controller = new CRM_Core_Controller_Simple(CRM_Case_Form_Case::class, ts('Case'), $action);
    $form->controller->_entryURL = $this->getCaseFormEntryUrl($caseCategoryId);
    $form->buildForm();

    return $form;
  }

}
