<?php

/**
 * @file
 * Declares an Angular module which can be autoloaded in CiviCRM.
 *
 * Core builds this declaration once and caches it for every user and URL
 * (Civi\Angular\Manager::getModules()), so it must not depend on, or act for,
 * the current request. Per-request work lives elsewhere:
 * - case category access: CRM_Civicase_Page_CaseAngular;
 * - page resources: CRM_Civicase_Event_Listener_CivicaseAngularResources;
 * - settings, word replacements and breadcrumbs: CRM_Civicase_Settings.
 *
 * See also:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules.
 */

use CRM_Civicase_Helper_GlobRecursive as GlobRecursive;

// Core can build the Angular module list more than once in a process (e.g.
// after a cache flush), including this file again, so only declare it once.
if (!function_exists('get_js_files')) {

  /**
   * Get a list of JS files.
   */
  function get_js_files() {
    return array_merge(
      [
        // At the moment, it's safe to include this multiple times.
        // deduped by resource manager.
        Civi::service('asset_builder')->getUrl('visual-bundle.js'),
        'ang/civicase.js',
      ],
      GlobRecursive::getRelativeToExtension(
        'uk.co.compucorp.civicase',
        'ang/civicase/*.js'
      )
    );
  }

}

$requires = [
  'crmAttachment',
  'crmUi',
  'ngRoute',
  'angularFileUpload',
  'crmRouteBinder',
  'crmResource',
  'ui.bootstrap',
  'uibTabsetClass',
  'dialogService',
  'civicase-base',
];
$requires = CRM_Civicase_Hook_addDependentAngularModules::invoke($requires);

return [
  'js' => get_js_files(),
  'css' => [
    // At the moment, it's safe to include this multiple times.
    // deduped by resource manager.
    Civi::service('asset_builder')->getUrl('visual-bundle.css'),
    'css/*.css',
  ],
  'partials' => [
    'ang/civicase',
  ],
  'settings' => [],
  'requires' => $requires,
  'basePages' => [],
  'permissions' => [
    'administer CiviCase',
    'administer CiviCRM',
    'access all cases and activities',
    'add cases',
    'basic case information',
    'access CiviCRM',
    'access my cases and activities',
  ],
  'settingsFactory' => ['CRM_Civicase_Settings', 'getAll'],
];
