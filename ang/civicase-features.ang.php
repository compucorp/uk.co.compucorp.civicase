<?php

/**
 * @file
 * Declares an Angular module which can be autoloaded in CiviCRM.
 *
 * See also:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules.
 */

use CRM_Civicase_Helper_GlobRecursive as GlobRecursive;

// Core can build the Angular module list more than once in a process (e.g.
// after a cache flush), including this file again, so only declare it once.
if (!function_exists('getFeaturesJsFiles')) {

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

}

$requires = [
  'api4',
  'crmSearchDisplayTable',
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
