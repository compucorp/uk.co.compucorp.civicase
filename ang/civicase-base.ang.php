<?php

/**
 * @file
 * Declares an Angular module which can be autoloaded in CiviCRM.
 *
 * See also:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules.
 */

return [
  'js' => CRM_Civicase_BaseAngular::get_js_files(),
  'settingsFactory' => ['CRM_Civicase_BaseAngular', 'getOptions'],
  'requires' => ['crmUtil', 'bw.paging'],
  'partials' => [
    'ang/civicase-base',
  ],
];
