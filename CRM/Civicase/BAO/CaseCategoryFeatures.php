<?php

/**
 * CaseCategoryFeatures BAO.
 */
class CRM_Civicase_BAO_CaseCategoryFeatures extends CRM_Civicase_DAO_CaseCategoryFeatures {

  /**
   * Create a new CaseCategoryFeatures based on array-data.
   *
   * @param array $params
   *   Key-value pairs.
   *
   * @return CRM_Civicase_DAO_CaseCategoryFeatures|null
   *   Case category feature.
   */
  public static function create(array $params) {
    $className = 'CRM_Civicase_DAO_CaseCategoryFeatures';
    $entityName = 'CaseCategoryFeatures';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

}
