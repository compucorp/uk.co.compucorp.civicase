<?php

/**
 * @file
 * CaseSalesOrder.Getlist API.
 */

use Civi\Api4\CaseSalesOrder;

/**
 * CaseSalesOrder.Getlist API specification (optional).
 *
 * This is used for documentation and validation.
 *
 * @param array $params
 *   Description of fields supported by this API call.
 * @param array $apiRequest
 *   API requesr.
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_case_sales_order_getlist_spec(&$params, $apiRequest) {
  require_once 'api/v3/Generic/Getlist.php';
  _civicrm_api3_generic_getlist_spec($params, $apiRequest);
}

/**
 * CaseSalesOrder.Getlist API.
 *
 * @param array $params
 *   API Params.
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_case_sales_order_getlist($params) {
  require_once 'api/v3/Generic/Getlist.php';
  $apiRequest = [
    'version' => 3,
    'entity' => 'CaseSalesOrder',
    'action' => 'getlist',
    'params' => $params,
  ];

  return civicrm_api3_generic_getList($apiRequest);
}

/**
 * Get case sales order list output.
 *
 * @param array $result
 *   API Result to format.
 * @param array $request
 *   API Request.
 *
 * @return array
 *   API Result
 *
 * @see _civicrm_api3_generic_getlist_output
 */
function _civicrm_api3_case_sales_order_getlist_output($result, $request) {
  $output = [];
  if (!empty($result['values'])) {
    foreach ($result['values'] as $row) {
      $caseSalesOrder = CaseSalesOrder::get()
        ->addSelect('contact.display_name', 'quotation_date')
        ->addJoin('Contact AS contact', 'LEFT', ['contact.id', '=', 'client_id'])
        ->addWhere('id', '=', $row['id'])
        ->execute()
        ->first();

      $data = [
        'id' => $row[$request['id_field']],
        'label' => "Client: {$caseSalesOrder['contact.display_name']}",
        'description' => [
          strip_tags($row['description']),
        ],
      ];
      $data['description'][] = "Quotation Date: " . CRM_Utils_Date::customFormat($caseSalesOrder['quotation_date']);
      $output[] = $data;
    }
  }
  return $output;
}
