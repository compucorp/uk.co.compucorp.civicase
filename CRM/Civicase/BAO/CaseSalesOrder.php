<?php

/**
 * CaseSalesOrder BAO.
 */
class CRM_Civicase_BAO_CaseSalesOrder extends CRM_Civicase_DAO_CaseSalesOrder {

  /**
   * Create a new CaseSalesOrder based on array-data.
   *
   * @param array $params
   *   Key-value pairs.
   *
   * @return CRM_Civicase_DAO_CaseSalesOrder|null
   *   Case sales order instance.
   */
  public static function create(array $params) {
    $className = 'CRM_Civicase_DAO_CaseSalesOrder';
    $entityName = 'CaseSalesOrder';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

}
