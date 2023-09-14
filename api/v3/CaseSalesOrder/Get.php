<?php

/**
 * @file
 * CaseSalesOrder.Get file.
 */

/**
 * CaseSalesOrder.Get API.
 *
 * @param array $params
 *   API Params.
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws API_Exception
 */
function civicrm_api3_case_sales_order_get($params) {
  $sql = CRM_Utils_SQL_Select::fragment();
  $salesOrders = _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params, FALSE, 'CaseSalesOrder', $sql, TRUE);

  return civicrm_api3_create_success($salesOrders, $params, 'CaseSalesOrder', 'get');
}

/**
 * CaseSalesOrder.Get API specification (optional).
 *
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_case_sales_order_get_spec(&$spec) {
  $fields = CRM_Civicase_BAO_CaseSalesOrder::fields();
  foreach ($fields as $fieldname => $field) {
    $spec[$fieldname] = $field;
  }
}
