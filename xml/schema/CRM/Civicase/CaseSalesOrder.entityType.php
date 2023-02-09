<?php

/**
 * @file
 * This file declares a new entity type.
 *
 * For more details, see "hook_civicrm_entityTypes" at:
 * https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes.
 */

return [
  [
    'name' => 'CaseSalesOrder',
    'class' => 'CRM_Civicase_DAO_CaseSalesOrder',
    'table' => 'civicrm_case_sales_order',
  ],
];
