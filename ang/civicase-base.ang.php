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
function get_base_js_files() {
  return array_merge(
    [
      Civi::service('asset_builder')->getUrl('visual-bundle.js'),
      'ang/civicase-base.js',
    ],
    GlobRecursive::getRelativeToExtension(
      'uk.co.compucorp.civicase',
      'ang/civicase-base/*.js'
    )
  );
}

return [
  'js' => get_base_js_files(),
  'settings' => [],
  'requires' => ['crmUtil', 'bw.paging'],
  'partials' => ['ang/civicase-base'],
  'settingsFactory' => ['CRM_Civicase_Settings', 'getAll'],
];
