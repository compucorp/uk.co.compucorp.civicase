<?php

/**
 * Fabricates cases.
 */
class CRM_Civicase_Test_Fabricator_Product {

  /**
   * Default Parameters.
   *
   * @var array
   */
  private static $defaultParams = [
    'name' => 'test',
    'description' => 'test',
    'sku' => 'test',
    'price' => 20,
  ];

  /**
   * Fabricate Case.
   *
   * @param array $params
   *   Parameters.
   *
   * @return mixed
   *   Api result.
   */
  public static function fabricate(array $params = []) {
    $params = array_merge(self::$defaultParams, $params);
    $result = civicrm_api4('Product', 'create', [
      'values' => $params,
    ])->jsonSerialize();

    return array_shift($result);
  }

}
