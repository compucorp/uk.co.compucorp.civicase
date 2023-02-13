<?php

/**
 * @file
 * This file declares a new entity type.
 *
 * For more details See "hook_civicrm_entityTypes" at:
 * https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes.
 */

return [
  [
    'name' => 'CaseSalesOrderLine',
    'class' => 'CRM_Civicase_DAO_CaseSalesOrderLine',
    'table' => 'civicase_sales_order_line',
  ],
];
