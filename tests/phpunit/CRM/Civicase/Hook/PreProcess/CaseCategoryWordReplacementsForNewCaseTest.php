<?php

use CRM_Civicase_Hook_PreProcess_CaseCategoryWordReplacementsForNewCase as CaseCategoryWordReplacementsForNewCase;

/**
 * Contains tests for the CaseCategoryWordReplacementsForNewCase class.
 *
 * @group headless
 */
class CRM_Civicase_Hook_PreProcess_CaseCategoryWordReplacementsForNewCaseTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_FormHookTestTrait;

  /**
   * Tests shouldRun returns TRUE for the New Case form.
   */
  public function testShouldRunReturnsTrueForNewCaseForm() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD, $this->getValidCaseCategoryId());

    $result = $this->invokePrivateMethod(new CaseCategoryWordReplacementsForNewCase(), 'shouldRun', [
      CRM_Case_Form_Case::class,
      $form,
    ]);

    $this->assertTrue($result);
  }

  /**
   * Tests shouldRun returns FALSE when the case form deletes or restores.
   *
   * Core doesn't assign the activity type used for the page title on these
   * forms, so the hook would set a blank title.
   *
   * @param int $action
   *   The form action.
   *
   * @dataProvider provideDeleteAndRestoreActions
   */
  public function testShouldRunReturnsFalseForDeleteAndRestoreActions($action) {
    $form = $this->buildCaseFormMock($action, $this->getValidCaseCategoryId());

    $result = $this->invokePrivateMethod(new CaseCategoryWordReplacementsForNewCase(), 'shouldRun', [
      CRM_Case_Form_Case::class,
      $form,
    ]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE for a form other than CRM_Case_Form_Case.
   */
  public function testShouldRunReturnsFalseForOtherFormName() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD, $this->getValidCaseCategoryId());

    $result = $this->invokePrivateMethod(new CaseCategoryWordReplacementsForNewCase(), 'shouldRun', [
      'CRM_Some_Other_Form',
      $form,
    ]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE for an unknown case category ID.
   */
  public function testShouldRunReturnsFalseForInvalidCaseCategoryId() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD, PHP_INT_MAX);

    $result = $this->invokePrivateMethod(new CaseCategoryWordReplacementsForNewCase(), 'shouldRun', [
      CRM_Case_Form_Case::class,
      $form,
    ]);

    $this->assertFalse($result);
  }

}
