<?php

use CRM_Civicase_Service_CaseCategoryCustomFieldsSetting as CaseCategoryCustomFieldsSetting;

/**
 * Category Helper class with useful functions for managing categories.
 */
class CRM_Civicase_Helper_Category {

  /**
   * Returns all the data of the category.
   *
   * @param string $categoryName
   *   Category name.
   *
   * @return int|null
   *   Case category data.
   */
  public static function get(string $categoryName) {
    $categoryData = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => 'case_type_categories',
      'name' => $categoryName,
    ]);

    if ($categoryData['count'] === 0) {
      return NULL;
    }
    $categoryData = array_shift($categoryData['values']);

    $categoryCustomFields = (new CaseCategoryCustomFieldsSetting())->get($categoryData['value']);
    $categoryData['singular_label'] = $categoryCustomFields['singular_label'] ?? NULL;

    return $categoryData;
  }

}
