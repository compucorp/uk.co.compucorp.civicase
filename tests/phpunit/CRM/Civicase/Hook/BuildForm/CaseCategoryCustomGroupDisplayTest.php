<?php

use CRM_Civicase_Hook_BuildForm_CaseCategoryCustomGroupDisplay as CaseCategoryCustomGroupDisplay;

/**
 * Contains tests for the CaseCategoryCustomGroupDisplay class.
 *
 * @group headless
 */
class CRM_Civicase_Hook_BuildForm_CaseCategoryCustomGroupDisplayTest extends BaseHeadlessTest {

  /**
   * Tests shouldRun returns TRUE for a valid case category custom group form.
   *
   * A valid form is one where:
   *  - The form name matches CRM_Custom_Form_Group.
   *  - The action is not ADD.
   *  - The `_defaultValues` property contains `extends` equal to 'Case'.
   *  - The `_defaultValues` property contains a non-empty
   *    `extends_entity_column_id`.
   */
  public function testShouldRunReturnsTrueForValidCaseCategoryCustomGroupForm() {
    $form = $this->buildFormMock([
      'extends' => 'Case',
      'extends_entity_column_id' => $this->getValidCaseCategoryId(),
    ], CRM_Core_Action::UPDATE);

    $result = $this->invokePrivateMethod('shouldRun', [CRM_Custom_Form_Group::class, $form]);

    $this->assertTrue($result);
  }

  /**
   * Tests shouldRun returns FALSE when `_defaultValues` has no `extends`.
   */
  public function testShouldRunReturnsFalseWhenExtendsIsMissing() {
    $form = $this->buildFormMock([
      'extends_entity_column_id' => $this->getValidCaseCategoryId(),
    ], CRM_Core_Action::UPDATE);

    $result = $this->invokePrivateMethod('shouldRun', [CRM_Custom_Form_Group::class, $form]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE when `extends` is not 'Case'.
   */
  public function testShouldRunReturnsFalseWhenExtendsIsNotCase() {
    $form = $this->buildFormMock([
      'extends' => 'Contact',
      'extends_entity_column_id' => $this->getValidCaseCategoryId(),
    ], CRM_Core_Action::UPDATE);

    $result = $this->invokePrivateMethod('shouldRun', [CRM_Custom_Form_Group::class, $form]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE when the form action is ADD.
   */
  public function testShouldRunReturnsFalseForAddAction() {
    $form = $this->buildFormMock([
      'extends' => 'Case',
      'extends_entity_column_id' => $this->getValidCaseCategoryId(),
    ], CRM_Core_Action::ADD);

    $result = $this->invokePrivateMethod('shouldRun', [CRM_Custom_Form_Group::class, $form]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE for a form other than CRM_Custom_Form_Group.
   */
  public function testShouldRunReturnsFalseForOtherFormName() {
    $form = $this->buildFormMock([
      'extends' => 'Case',
      'extends_entity_column_id' => $this->getValidCaseCategoryId(),
    ], CRM_Core_Action::UPDATE);

    $result = $this->invokePrivateMethod('shouldRun', ['CRM_Some_Other_Form', $form]);

    $this->assertFalse($result);
  }

  /**
   * Tests shouldRun returns FALSE when `extends_entity_column_id` is empty.
   */
  public function testShouldRunReturnsFalseWhenExtendsEntityColumnIdIsEmpty() {
    $form = $this->buildFormMock([
      'extends' => 'Case',
      'extends_entity_column_id' => NULL,
    ], CRM_Core_Action::UPDATE);

    $result = $this->invokePrivateMethod('shouldRun', [CRM_Custom_Form_Group::class, $form]);

    $this->assertFalse($result);
  }

  /**
   * Tests setDefaultFormValueForCaseCategory sets the correct default values.
   *
   * The method should:
   *  - Set `extends` to the case category name.
   *  - Reset `extends_entity_column_value` to an empty array in defaults.
   *  - Apply the updated defaults on the form.
   *  - Clear the hierSelect's `data-select-params` data option.
   *  - Set the value of the `extends` select to the category name.
   *  - Clear the value of the `extends_entity_column_value` hierSelect.
   */
  public function testSetDefaultFormValueSetsCorrectDefaults() {
    $caseCategoryId = $this->getValidCaseCategoryId();
    $caseTypeCategories = CRM_Case_BAO_CaseType::buildOptions('case_type_category', 'validate');
    $caseCategoryName = $caseTypeCategories[$caseCategoryId];

    $defaults = [
      'extends' => 'Case',
      'extends_entity_column_id' => $caseCategoryId,
    ];

    $extendsElement = $this->createMock(HTML_QuickForm_element::class);
    $hierSelectElement = $this->createMock(HTML_QuickForm_element::class);
    $hierSelectElement->method('getAttributes')->willReturn([]);

    $form = $this->buildFormMockWithElements($defaults, CRM_Core_Action::UPDATE, [
      'extends' => $extendsElement,
      'extends_entity_column_value' => $hierSelectElement,
    ]);

    $form->expects($this->once())
      ->method('setDefaults')
      ->with($this->callback(function ($values) use ($caseCategoryName) {
        return isset($values['extends'])
          && $values['extends'] === $caseCategoryName
          && array_key_exists('extends_entity_column_value', $values)
          && $values['extends_entity_column_value'] === [];
      }));

    $extendsElement->expects($this->once())
      ->method('setValue')
      ->with($caseCategoryName);

    $hierSelectElement->expects($this->once())
      ->method('setValue')
      ->with(NULL);

    $hierSelectElement->expects($this->once())
      ->method('setAttributes')
      ->with($this->callback(function ($attrs) {
        return isset($attrs['data-select-params'])
          && $attrs['data-select-params'] === json_encode(['data' => []]);
      }));

    $this->invokePrivateMethod('setDefaultFormValueForCaseCategory', [$form]);
  }

  /**
   * Tests setDefaultFormValueForCaseCategory returns early on empty column id.
   *
   * The form should not be mutated in any way when
   * `extends_entity_column_id` is empty in the default values.
   */
  public function testSetDefaultFormValueReturnsEarlyWhenEntityColumnIdIsEmpty() {
    $defaults = [
      'extends' => 'Case',
      'extends_entity_column_id' => NULL,
    ];

    $form = $this->buildFormMock($defaults, CRM_Core_Action::UPDATE);
    $form->expects($this->never())->method('setDefaults');
    $form->expects($this->never())->method('getElement');

    $this->invokePrivateMethod('setDefaultFormValueForCaseCategory', [$form]);
  }

  /**
   * Tests setDefaultFormValueForCaseCategory returns early for an unknown id.
   *
   * The form should not be mutated in any way when the provided
   * `extends_entity_column_id` is not a key in the list of case type
   * categories returned by CRM_Case_BAO_CaseType::buildOptions.
   */
  public function testSetDefaultFormValueReturnsEarlyForUnknownCategoryId() {
    $defaults = [
      'extends' => 'Case',
      'extends_entity_column_id' => PHP_INT_MAX,
    ];

    $form = $this->buildFormMock($defaults, CRM_Core_Action::UPDATE);
    $form->expects($this->never())->method('setDefaults');
    $form->expects($this->never())->method('getElement');

    $this->invokePrivateMethod('setDefaultFormValueForCaseCategory', [$form]);
  }

  /**
   * Returns the first valid case type category ID from the system.
   *
   * @return int
   *   A valid case type category ID.
   */
  private function getValidCaseCategoryId() {
    $caseTypeCategories = CRM_Case_BAO_CaseType::buildOptions('case_type_category', 'validate');

    return (int) array_key_first($caseTypeCategories);
  }

  /**
   * Builds a mocked CRM_Core_Form for tests.
   *
   * The returned form returns the provided $defaultValues when `getVar` is
   * called with `_defaultValues`, and the provided $action when `getVar`
   * is called with `_action`.
   *
   * @param array $defaultValues
   *   Values to be returned by the `_defaultValues` variable.
   * @param int $action
   *   Value to be returned by the `_action` variable.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mocked form object.
   */
  private function buildFormMock(array $defaultValues, $action) {
    $form = $this->getMockBuilder(CRM_Core_Form::class)
      ->disableOriginalConstructor()
      ->getMock();
    $form->method('getVar')->willReturnCallback(
      function ($name) use ($defaultValues, $action) {
        if ($name === '_defaultValues') {
          return $defaultValues;
        }
        if ($name === '_action') {
          return $action;
        }

        return NULL;
      }
    );

    return $form;
  }

  /**
   * Builds a mocked form object with form elements attached.
   *
   * @param array $defaultValues
   *   Values to be returned by the `_defaultValues` variable.
   * @param int $action
   *   Value to be returned by the `_action` variable.
   * @param array $elements
   *   Array of mocked elements keyed by element name.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mocked form object.
   */
  private function buildFormMockWithElements(array $defaultValues, $action, array $elements) {
    $form = $this->buildFormMock($defaultValues, $action);
    $form->method('getElement')->willReturnCallback(
      function ($name) use ($elements) {
        return $elements[$name] ?? NULL;
      }
    );

    return $form;
  }

  /**
   * Invokes a private method of the hook class via a bound closure.
   *
   * A bound closure is used rather than ReflectionMethod::invokeArgs so
   * that parameters declared as pass-by-reference (for example the
   * `CRM_Core_Form &$form` parameter of
   * `setDefaultFormValueForCaseCategory`) are bound correctly without
   * the caller having to build a reference array by hand.
   *
   * @param string $methodName
   *   The name of the private method.
   * @param array $arguments
   *   Arguments to pass to the method.
   *
   * @return mixed
   *   The return value of the invoked method.
   */
  private function invokePrivateMethod($methodName, array $arguments) {
    $hook = new CaseCategoryCustomGroupDisplay();
    $closure = Closure::bind(
      function () use ($methodName, $arguments) {
        return $this->{$methodName}(...$arguments);
      },
      $hook,
      CaseCategoryCustomGroupDisplay::class
    );

    return $closure();
  }

}
