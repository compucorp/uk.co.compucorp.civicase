<?php

/**
 * @file
 * Declares an Angular module which can be autoloaded in CiviCRM.
 *
 * See also:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules.
 */

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
  'js' => CRM_Civicase_Angular::get_js_files(),
  'css' => [
    // At the moment, it's safe to include this multiple times.
    // deduped by resource manager.
    'assetBuilder://visual-bundle.css',
    'css/*.css',
  ],
  'partials' => [
    'ang/civicase',
  ],
  'settingsFactory' => ['CRM_Civicase_Angular', 'getOptions'],
  'requires' => $requires,
  'basePages' => [],
];
