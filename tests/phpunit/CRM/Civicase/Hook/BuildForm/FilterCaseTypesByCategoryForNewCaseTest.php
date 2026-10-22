<?php

use CRM_Civicase_Hook_BuildForm_FilterCaseTypesByCategoryForNewCase as FilterCaseTypesByCategoryForNewCase;
use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;

/**
 * Contains tests for the FilterCaseTypesByCategoryForNewCase class.
 *
 * @group headless
 */
class CRM_Civicase_Hook_BuildForm_FilterCaseTypesByCategoryForNewCaseTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_FormHookTestTrait;

  /**
   * Tests shouldRun returns TRUE for the New Case form.
   *
   * The New Case form is CRM_Case_Form_Case with the ADD action, and the
   * case category from the URL must be valid.
   */
  public function testShouldRunReturnsTrueForNewCaseForm() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD, $this->getValidCaseCategoryId());

    $result = $this->invokePrivateMethod(new FilterCaseTypesByCategoryForNewCase(), 'shouldRun', [
      $form,
      CRM_Case_Form_Case::class,
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
    $form = $this->buildCaseFormMock($action, $this->getValidCaseCategoryId());

    $result = $this->invokePrivateMethod(new FilterCaseTypesByCategoryForNewCase(), 'shouldRun', [
      $form,
      CRM_Case_Form_Case::class,
    ]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE for a form other than CRM_Case_Form_Case.
   */
  public function testShouldRunReturnsFalseForOtherFormName() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD, $this->getValidCaseCategoryId());

    $result = $this->invokePrivateMethod(new FilterCaseTypesByCategoryForNewCase(), 'shouldRun', [
      $form,
      'CRM_Some_Other_Form',
    ]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE for an unknown case category ID.
   */
  public function testShouldRunReturnsFalseForInvalidCaseCategoryId() {
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD, PHP_INT_MAX);

    $result = $this->invokePrivateMethod(new FilterCaseTypesByCategoryForNewCase(), 'shouldRun', [
      $form,
      CRM_Case_Form_Case::class,
    ]);

    $this->assertFalse($result);
  }

  /**
   * Tests run keeps only the case types of the category on the New Case form.
   *
   * The empty "- select -" option is kept as well.
   */
  public function testRunFiltersCaseTypeOptionsByCategory() {
    $caseCategory = CaseCategoryFabricator::fabricate();
    $otherCaseCategory = CaseCategoryFabricator::fabricate();
    CRM_Core_PseudoConstant::flush();
    $caseTypeInCategory = CaseTypeFabricator::fabricate([
      'name' => 'filter_test_case_type_in_category',
      'title' => 'Filter Test Case Type In Category',
      'case_type_category' => $caseCategory['value'],
    ]);
    $caseTypeInOtherCategory = CaseTypeFabricator::fabricate([
      'name' => 'filter_test_case_type_in_other_category',
      'title' => 'Filter Test Case Type In Other Category',
      'case_type_category' => $otherCaseCategory['value'],
    ]);
    Civi::cache()->clear();

    $caseTypeElement = new HTML_QuickForm_select('case_type_id', 'Case Type', [
      '' => '- select -',
      $caseTypeInCategory['id'] => $caseTypeInCategory['title'],
      $caseTypeInOtherCategory['id'] => $caseTypeInOtherCategory['title'],
    ]);
    $form = $this->buildCaseFormMock(CRM_Core_Action::ADD, $caseCategory['value']);
    $form->method('getElement')->with('case_type_id')->willReturn($caseTypeElement);

    $hook = new FilterCaseTypesByCategoryForNewCase();
    $hook->run($form, CRM_Case_Form_Case::class);

    $optionValues = array_map(function ($option) {
      return (string) $option['attr']['value'];
    }, $caseTypeElement->_options);
    sort($optionValues);
    $this->assertEquals(['', (string) $caseTypeInCategory['id']], $optionValues);
  }

}
