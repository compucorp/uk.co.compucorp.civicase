<?php

use CRM_Civicase_Hook_PreProcess_CaseTypeCategoryWebFormRedirect as CaseTypeCategoryWebFormRedirect;

/**
 * Contains tests for the CaseTypeCategoryWebFormRedirect class.
 *
 * @group headless
 */
class CRM_Civicase_Hook_PreProcess_CaseTypeCategoryWebFormRedirectTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_FormHookTestTrait;

  /**
   * {@inheritDoc}
   */
  public function tearDown(): void {
    unset($_GET['snippet']);
  }

  /**
   * Tests shouldRun returns TRUE for the New Case form.
   */
  public function testShouldRunReturnsTrueForNewCaseForm() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD);

    $result = $this->invokePrivateMethod(new CaseTypeCategoryWebFormRedirect(), 'shouldRun', [
      CRM_Case_Form_Case::class,
      $form,
    ]);

    $this->assertTrue($result);
  }

  /**
   * Tests shouldRun returns FALSE when the case form deletes or restores.
   *
   * @param int $action
   *   The form action.
   *
   * @dataProvider provideDeleteAndRestoreActions
   */
  public function testShouldRunReturnsFalseForDeleteAndRestoreActions($action) {
    $form = $this->buildCaseFormMock($action);

    $result = $this->invokePrivateMethod(new CaseTypeCategoryWebFormRedirect(), 'shouldRun', [
      CRM_Case_Form_Case::class,
      $form,
    ]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE when the form is loaded as a JSON snippet.
   */
  public function testShouldRunReturnsFalseForJsonSnippet() {
    $_GET['snippet'] = 'json';
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD);

    $result = $this->invokePrivateMethod(new CaseTypeCategoryWebFormRedirect(), 'shouldRun', [
      CRM_Case_Form_Case::class,
      $form,
    ]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE for a form other than CRM_Case_Form_Case.
   */
  public function testShouldRunReturnsFalseForOtherFormName() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD);

    $result = $this->invokePrivateMethod(new CaseTypeCategoryWebFormRedirect(), 'shouldRun', [
      'CRM_Some_Other_Form',
      $form,
    ]);

    $this->assertFalse($result);
  }

}
