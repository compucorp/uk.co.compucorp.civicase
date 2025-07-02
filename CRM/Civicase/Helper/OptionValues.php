<?php

/**
 * OptionValue Helper class with useful functions that sets JS variables.
 */
class CRM_Civicase_Helper_OptionValues {

  /**
   * Sets the option values to javascript global variable.
   */
  public static function setToJsVariables(&$options) {
    foreach ($options as &$option) {
      $result = civicrm_api3('OptionValue', 'get', [
        'return' => [
          'value', 'label', 'color', 'icon', 'name', 'grouping',
          'is_active', 'weight', 'filter',
        ],
        'option_group_id' => $option,
        'options' => [
          'limit' => 0,
          'sort' => 'weight',
          'cache' => TRUE,
        ],
      ]);
      $option = [];
      foreach ($result['values'] as $item) {
        $key = $item['value'];
        CRM_Utils_Array::remove($item, 'id');
        $option[$key] = $item;
      }
    }
  }

}
