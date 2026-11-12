<?php

/**
 * @file
 * Declares an Angular module which can be autoloaded in CiviCRM.
 *
 * See also:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules.
 */

use CRM_Civicase_Helper_CaseCategory as CaseCategoryHelper;
use CRM_Civicase_Helper_CaseUrl as CaseUrlHelper;
use CRM_Civicase_Helper_GlobRecursive as GlobRecursive;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;

// Core can build the Angular module list more than once in a process (e.g.
// after a cache flush), including this file again, so only declare it once.
if (!function_exists('load_resources')) {

  /**
   * Loads Resources.
   */
  function load_resources() {
    Civi::resources()
      ->addScriptFile('org.civicrm.shoreditch', 'base/js/affix.js', 1000, 'html-header')
      ->addSetting([
        'config' => [
          'enableComponents' => CRM_Core_Config::singleton()->enableComponents,
          'user_contact_id' => (int) CRM_Core_Session::getLoggedInContactID(),
        ],
      ]);
  }

}

if (!function_exists('adds_shoreditch_css')) {

  /**
   * Add shoreditch custom css if not already present.
   */
  function adds_shoreditch_css() {
    if (!civicrm_api3('Setting', 'getvalue', ['name' => "customCSSURL"])) {
      Civi::resources()
        ->addStyleFile('org.civicrm.shoreditch', 'css/custom-civicrm.css', 99, 'html-header');
    }
  }

}

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

load_resources();
[$caseCategoryId, $caseCategoryName] = CaseUrlHelper::getCategoryParamsFromUrl();

// Word replacements are already loaded for the contact tab ContactCaseTab.
if (CRM_Utils_System::currentPath() !== 'civicrm/case/contact-case-tab') {
  $notTranslationPath = $caseCategoryName == CaseCategoryHelper::CASE_TYPE_CATEGORY_NAME && CRM_Utils_System::currentPath() != 'civicrm/case/a';

  if (!$notTranslationPath) {
    if (!in_array($caseCategoryName, CaseCategoryHelper::getAccessibleCaseTypeCategories())) {
      throw new Exception('Access denied! You are not authorized to access this page.');
    }
  }
}

$permissionService = new CaseCategoryPermission();
$caseCategoryPermissions = $permissionService->get($caseCategoryName);

// The following changes are only relevant to the full-page app.
if (CRM_Utils_System::currentPath() == 'civicrm/case/a') {
  adds_shoreditch_css();
  CaseCategoryHelper::updateBreadcrumbs($caseCategoryId);
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
