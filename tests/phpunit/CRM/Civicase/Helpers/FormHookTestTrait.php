<?php

/**
 * Helpers for testing form hook classes.
 */
trait CRM_Civicase_Helpers_FormHookTestTrait {

  /**
   * Invokes a private method of an object via a bound closure.
   *
   * @param object $object
   *   The object that owns the private method.
   * @param string $methodName
   *   The name of the private method.
   * @param array $arguments
   *   Arguments to pass to the method.
   *
   * @return mixed
   *   The return value of the invoked method.
   */
  private function invokePrivateMethod($object, $methodName, array $arguments) {
    $closure = Closure::bind(
      function () use ($methodName, $arguments) {
        return $this->{$methodName}(...$arguments);
      },
      $object,
      get_class($object)
    );

    return $closure();
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
   * Provides the case form actions used to delete and restore cases.
   *
   * @return array
   *   List of actions.
   */
  public static function provideDeleteAndRestoreActions() {
    return [
      'delete' => [CRM_Core_Action::DELETE],
      'restore' => [CRM_Core_Action::RENEW],
    ];
  }

  /**
   * Builds a mocked case form for the given action and case category.
   *
   * The form's entry URL contains the `case_type_category` parameter, the
   * same way the civicase "New Case" links do.
   *
   * @param int $action
   *   The form action (e.g. CRM_Core_Action::ADD).
   * @param string|int|null $caseCategoryId
   *   The case category ID to add to the entry URL. No category parameter
   *   is added when empty.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|CRM_Core_Form
   *   The mocked form object.
   */
  private function buildCaseFormMock($action, $caseCategoryId = NULL) {
    $form = $this->getMockBuilder(CRM_Core_Form::class)
      ->disableOriginalConstructor()
      ->getMock();
    $form->method('getAction')->willReturn($action);
    $form->controller = (object) [
      '_entryURL' => $this->getCaseFormEntryUrl($caseCategoryId),
    ];

    return $form;
  }

  /**
   * Returns a case form entry URL for the given case category.
   *
   * @param string|int|null $caseCategoryId
   *   The case category ID to add to the URL. No category parameter is
   *   added when empty.
   *
   * @return string
   *   The entry URL.
   */
  private function getCaseFormEntryUrl($caseCategoryId = NULL) {
    $query = ['reset' => 1];
    if ($caseCategoryId) {
      $query['case_type_category'] = $caseCategoryId;
    }

    return 'civicrm/case/add?' . http_build_query($query);
  }

}
