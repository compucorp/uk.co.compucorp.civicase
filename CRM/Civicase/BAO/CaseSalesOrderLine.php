<?php

/**
 * CaseSalesOrderLine BAO.
 */
class CRM_Civicase_BAO_CaseSalesOrderLine extends CRM_Civicase_DAO_CaseSalesOrderLine {

  /**
   * Create a new CaseSalesOrderLine based on array-data.
   *
   * @param array $params
   *   Key-value pairs.
   *
   * @return CRM_Civicase_DAO_CaseSalesOrderLine|null
   *   CRM_Civicase_DAO_CaseSalesOrderLine
   */
  public static function create(array $params) {
    $className = 'CRM_Civicase_DAO_CaseSalesOrderLine';
    $entityName = 'CaseSalesOrderLine';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

}
