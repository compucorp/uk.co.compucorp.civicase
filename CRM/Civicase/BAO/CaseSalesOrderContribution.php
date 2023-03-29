<?php

/**
 * CaseSalesOrderContribution BAO.
 */
class CRM_Civicase_BAO_CaseSalesOrderContribution extends CRM_Civicase_DAO_CaseSalesOrderContribution {

  /**
   * Create a new CaseSalesOrderContribution based on array-data.
   *
   * @param array $params
   *   Key-value pairs.
   *
   * @return CRM_Civicase_DAO_CaseSalesOrderContribution|null
   *   Case sales order contribution instance.
   */
  public static function create(array $params) {
    $className = 'CRM_Civicase_DAO_CaseSalesOrderContribution';
    $entityName = 'CaseSalesOrderContribution';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

}
