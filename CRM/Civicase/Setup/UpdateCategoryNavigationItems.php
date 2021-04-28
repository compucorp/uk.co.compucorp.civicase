<?php

/**
 * Class for updating menus of Case Categories dynamically created.
 */
class CRM_Civicase_Setup_UpdateCategoryNavigationItems {

  /**
   * Update category navigation items.
   */
  public function apply() {
    $categories = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'case_type_categories',
    ])['values'];

    foreach ($categories as $category) {
      $items = civicrm_api3('Navigation', 'get', [
        'sequential' => 1,
        'url' => ['LIKE' => "%case_type_category={$category['name']}%"],
      ])['values'];

      foreach ($items as $item) {
        civicrm_api3('Navigation', 'get', [
          'id' => $item['id'],
          'api.Navigation.create' => [
            'id' => '$value.id',
            'url' => str_ireplace($category['name'], $category['value'], $item['url']),
          ],
        ]);
      }
    }

    CRM_Core_BAO_Navigation::resetNavigation();
  }
}
