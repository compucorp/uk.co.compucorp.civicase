<?php

use CRM_Civicase_Hook_BuildForm_CaseCategoryFormLabelTranslationForNewCase as CaseCategoryFormLabelTranslationForNewCase;
use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;

/**
 * Contains tests for the CaseCategoryFormLabelTranslationForNewCase class.
 *
 * @group headless
 */
class CRM_Civicase_Hook_BuildForm_CaseCategoryFormLabelTranslationForNewCaseTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_FormHookTestTrait;

  /**
   * Tests shouldRun returns TRUE for the New Case form of another category.
   */
  public function testShouldRunReturnsTrueForNewCaseFormOfNonDefaultCategory() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD, $this->createCaseCategory());

    $result = $this->invokePrivateMethod(new CaseCategoryFormLabelTranslationForNewCase(), 'shouldRun', [
      $form,
      CRM_Case_Form_Case::class,
    ]);

    $this->assertTrue($result);
  }

  /**
   * Tests shouldRun returns FALSE for the default "Cases" category.
   */
  public function testShouldRunReturnsFalseForDefaultCaseCategory() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD, CRM_Civicase_Helper_CaseCategory::getOptionValue());

    $result = $this->invokePrivateMethod(new CaseCategoryFormLabelTranslationForNewCase(), 'shouldRun', [
      $form,
      CRM_Case_Form_Case::class,
    ]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE when the case form deletes or restores.
   *
   * The case type and status fields don't exist on these forms.
   *
   * @param int $action
   *   The form action.
   *
   * @dataProvider provideDeleteAndRestoreActions
   */
  public function testShouldRunReturnsFalseForDeleteAndRestoreActions($action) {
    $form = $this->buildCaseFormMock($action, $this->createCaseCategory());

    $result = $this->invokePrivateMethod(new CaseCategoryFormLabelTranslationForNewCase(), 'shouldRun', [
      $form,
      CRM_Case_Form_Case::class,
    ]);

    $this->assertFalse($result);
  }

  /**
   * Creates a case category other than the default "Cases" one.
   *
   * @return string|int
   *   The case category ID.
   */
  private function createCaseCategory() {
    $caseCategory = CaseCategoryFabricator::fabricate();
    CRM_Core_PseudoConstant::flush();

    return $caseCategory['value'];
  }

}
