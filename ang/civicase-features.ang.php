<?php

/**
 * @file
 * Declares an Angular module which can be autoloaded in CiviCRM.
 *
 * See also:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules.
 */

use Civi\Api4\OptionValue;
use Civi\Utils\CurrencyUtils;
use CRM_Civicase_Helper_GlobRecursive as GlobRecursive;
use CRM_Civicase_Service_CaseTypeCategoryFeatures as CaseTypeCategoryFeatures;

$options = [];

set_currency_codes($options);
set_case_sales_order_status($options);
set_case_types_with_features_enabled($options);

/**
 * Get a list of JS files.
 *
 * @return array
 *   list of js files
 */
function getFeaturesJsFiles() {
  return array_merge(
    [
      'ang/civicase-features.js',
    ],
    GlobRecursive::getRelativeToExtension(
      'uk.co.compucorp.civicase',
      'ang/civicase-features/*.js'
    )
  );
}

/**
 * Exposes currency codes to Angular.
 */
function set_currency_codes(&$options) {
  $options['currencyCodes'] = CurrencyUtils::getCurrencies();
}

/**
 * Exposes Case types that have features enabled to Angular.
 */
function set_case_types_with_features_enabled(&$options) {
  $caseTypeCategoryFeatures = new CaseTypeCategoryFeatures();

  array_map(function ($feature) use ($caseTypeCategoryFeatures, &$options) {
    $caseTypeCategories = $caseTypeCategoryFeatures->retrieveCaseInstanceWithEnabledFeatures([$feature]);
    $options['featureCaseTypes'][$feature] = array_keys($caseTypeCategories);
  }, ['quotations', 'invoices']);
}

/**
 * Exposes case sales order statuses to Angular.
 */
function set_case_sales_order_status(&$options) {
  $optionValues = OptionValue::get()
    ->addSelect('id', 'value', 'name', 'label')
    ->addWhere('option_group_id:name', '=', 'case_sales_order_status')
    ->execute();

  $options['salesOrderStatus'] = $optionValues->getArrayCopy();
}

$requires = [
  'api4',
  'crmUi',
  'crmUtil',
  'civicase',
  'civicase-base',
  'afsearchQuotations',
  'afsearchContactQuotations',
  'afsearchQuotationInvoices',
];

return [
  'css' => [
    'css/*.css',
  ],
  'js' => getFeaturesJsFiles(),
  'settings' => $options,
  'requires' => $requires,
  'partials' => [
    'ang/civicase-features',
  ],
];
