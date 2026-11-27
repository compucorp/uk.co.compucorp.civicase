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
if (!function_exists('get_my_activities_js_files')) {

  /**
   * Get a list of JS files.
   *
   * @return array
   *   list of js files
   */
  function get_my_activities_js_files() {
    return array_merge(
      [
        'ang/my-activities.js',
      ],
      GlobRecursive::getRelativeToExtension(
        'uk.co.compucorp.civicase',
        'ang/my-activities/*.js'
      )
    );
  }

}

$requires = [
  'civicase',
];

return [
  'css' => [
    'css/*.css',
  ],
  'js' => get_my_activities_js_files(),
  'requires' => $requires,
  'partials' => [
    'ang/my-activities',
  ],
];
