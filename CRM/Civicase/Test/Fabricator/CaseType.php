<?php

/**
 * Fabricates case types.
 */
class CRM_Civicase_Test_Fabricator_CaseType {

  /**
   * Default Parameters.
   *
   * @var array
   */
  private static $defaultParams = [
    'title' => 'test case type',
    'name' => 'test_case_type',
    'is_active' => 1,
    'sequential'   => 1,
    'weight' => 100,
    'definition' => [
      'activityTypes' => [
        ['name' => 'Meeting'],
      ],
      'activitySets' => [
        [
          'name' => 'set1',
          'label' => 'Label 1',
          'timeline' => 1,
          'activityTypes' => [
            ['name' => 'Open Case', 'status' => 'Completed'],
          ],
        ],
      ],
    ],
  ];

  /**
   * Fabricates new CaseType entity.
   *
   * @param array $params
   *   Case parameters.
   *
   * @return array
   *   Values of newly created Case entity.
   */
  public static function fabricate(array $params = []) {
    $params = array_merge(self::$defaultParams, $params);
    $result = civicrm_api3(
      'CaseType',
      'create',
      $params
    );

    return array_shift($result['values']);
  }

}
