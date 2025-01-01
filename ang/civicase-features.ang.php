<?php

/**
 * @file
 * Declares an Angular module which can be autoloaded in CiviCRM.
 *
 * See also:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules.
 */

use CRM_Civicase_Helper_GlobRecursive as GlobRecursive;

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

$requires = [
  'api4',
  'crmUi',
  'crmUtil',
  'civicase',
  'ui.sortable',
  'dialogService',
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
  'settingsFactory' => ['CRM_Civicase_Settings', 'getFeaturesSettings'],
  'requires' => $requires,
  'partials' => [
    'ang/civicase-features',
  ],
];
