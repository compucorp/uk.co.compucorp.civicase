<?php

/**
 * @file
 * Declares an Angular module which can be autoloaded in CiviCRM.
 *
 * See also:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules.
 */

use CRM_Civicase_Helper_GlobRecursive as GlobRecursive;

load_resources();
$caseCategoryName = CRM_Utils_Request::retrieve('case_type_category', 'String');
CRM_Civicase_Hook_Helper_CaseTypeCategory::addWordReplacements($caseCategoryName);


// The following changes are only relevant to the full-page app.
if (CRM_Utils_System::getUrlPath() == 'civicrm/case/a') {
  adds_shoreditch_css();
  update_breadcrumbs();
}

$options = [];

$requires = [
  'crmAttachment',
  'crmUi',
  'crmUtil',
  'ngRoute',
  'angularFileUpload',
  'bw.paging',
  'crmRouteBinder',
  'crmResource',
  'ui.bootstrap',
  'uibTabsetClass',
  'dialogService',
  'civicase-base',
];
$requires = CRM_Civicase_Hook_addDependentAngularModules::invoke($requires);

set_case_actions($options);
set_contact_tasks($options);
expose_settings($options);
retrieve_civicase_webform_url($options);

/**
 * Loads Resources.
 */
function load_resources() {
  Civi::resources()
    ->addPermissions([
      'administer CiviCase', 'administer CiviCRM',
      'access all cases and activities', 'add cases', 'basic case information',
    ])
    ->addScriptFile('org.civicrm.shoreditch', 'base/js/affix.js', 1000, 'html-header')
    ->addSetting([
      'config' => [
        'enableComponents' => CRM_Core_Config::singleton()->enableComponents,
        'user_contact_id' => (int) CRM_Core_Session::getLoggedInContactID(),
      ],
    ]);
}

/**
 * Add shoreditch custom css if not already present.
 */
function adds_shoreditch_css() {
  if (!civicrm_api3('Setting', 'getvalue', ['name' => "customCSSURL"])) {
    Civi::resources()
      ->addStyleFile('org.civicrm.shoreditch', 'css/custom-civicrm.css', 99, 'html-header');
  }
}

/**
 * Update Breadcrumbs.
 */
function update_breadcrumbs() {
  CRM_Utils_System::resetBreadCrumb();
  $breadcrumb = [
    [
      'title' => ts('Home'),
      'url' => CRM_Utils_System::url(),
    ],
    [
      'title' => ts('CiviCRM'),
      'url' => CRM_Utils_System::url('civicrm', 'reset=1'),
    ],
    [
      'title' => ts('Case Dashboard'),
      'url' => CRM_Utils_System::url('civicrm/case/a/#/case'),
    ],
  ];
  CRM_Utils_System::appendBreadCrumb($breadcrumb);
}

/**
 * Returns the contact number of the client for given webform id.
 *
 * @param int $webform_id
 *   Webform id.
 *
 * @return int
 *   Contact Number.
 */
function get_client_delta_from_webform($webform_id) {
  $node = node_load($webform_id);
  $data = $node->webform_civicrm['data'];
  $client = 0;
  if (isset($data['case'][1]['case'][1]['client_id'])) {
    $clients = $data['case'][1]['case'][1]['client_id'];
    $client = reset($clients);
  }

  return $client;
}

/**
 * Get a list of JS files.
 */
function get_js_files() {
  return array_merge([
    // At the moment, it's safe to include this multiple times.
    // deduped by resource manager.
    'assetBuilder://visual-bundle.js',
    'ang/civicase.js',
  ], GlobRecursive::get(dirname(__FILE__) . '/civicase/*.js'));
}

/**
 * Bulk actions for case list.
 *
 * We put this here so it can be modified by other extensions.
 */
function set_case_actions(&$options) {
  $options['caseActions'] = [
    [
      'title' => ts('Change Case Status'),
      'action' => 'ChangeStatus',
      'icon' => 'fa-pencil-square-o',
    ],
    [
      'title' => ts('Edit Tags'),
      'action' => 'EditTags',
      'icon' => 'fa-tags',
      'number' => 1,
    ],
    [
      'title' => ts('Print Case'),
      'action' => 'Print',
      'number' => 1,
      'icon' => 'fa-print',
    ],
    [
      'title' => ts('Email Case Manager'),
      'action' => 'EmailManagers',
      'icon' => 'fa-envelope-o',
    ],
    [
      'title' => ts('Print/Merge Document'),
      'action' => 'PrintMerge',
      'icon' => 'fa-file-pdf-o',
    ],
    [
      'title' => ts('Export Cases'),
      'action' => 'ExportCases',
      'icon' => 'fa-file-excel-o',
    ],
    [
      'title' => ts('Link Cases'),
      'action' => 'LinkCases',
      'number' => 1,
      'icon' => 'fa-link',
    ],
    [
      'title' => ts('Link 2 Cases'),
      'action' => 'LinkCases',
      'number' => 2,
      'icon' => 'fa-link',
    ],
  ];
  if (CRM_Core_Permission::check('administer CiviCase')) {
    $options['caseActions'][] = [
      'title' => ts('Merge 2 Cases'),
      'number' => 2,
      'action' => 'MergeCases',
      'icon' => 'fa-compress',
    ];
    $options['caseActions'][] = [
      'title' => ts('Lock Case'),
      'action' => 'LockCases',
      'number' => 1,
      'icon' => 'fa-lock',
    ];
  }
  if (CRM_Core_Permission::check('delete in CiviCase')) {
    $options['caseActions'][] = [
      'title' => ts('Delete Case'),
      'action' => 'DeleteCases',
      'icon' => 'fa-trash',
    ];
  }

  add_webforms_case_action($options);
}

/**
 * Add webforms with cases attached to menu.
 */
function add_webforms_case_action(&$options) {
  $items = [];

  $webformsToDisplay = Civi::settings()->get('civi_drupal_webforms');
  if (isset($webformsToDisplay)) {
    $allowedWebforms = [];
    foreach ($webformsToDisplay as $webformNode) {
      $allowedWebforms[] = $webformNode['nid'];
    }
    $webforms = civicrm_api3('Case', 'getwebforms');
    if (isset($webforms['values'])) {
      foreach ($webforms['values'] as $webform) {
        if (!in_array($webform['nid'], $allowedWebforms)) {
          continue;
        }

        $client = get_client_delta_from_webform($webform['nid']);

        $items[] = [
          'title' => $webform['title'],
          'action' => 'GoToWebform',
          'path' => $webform['path'],
          'clientID' => $client,
          'icon' => 'fa-link',
        ];
      }
      $options['caseActions'][] = [
        'title' => ts('Webforms'),
        'action' => '',
        'icon' => 'fa-file-text-o',
        'items' => $items,
      ];
    }
  }
}

/**
 * Sets contact tasks.
 */
function set_contact_tasks(&$options) {
  $contactTasks = CRM_Contact_Task::permissionedTaskTitles(CRM_Core_Permission::getPermission());
  $options['contactTasks'] = [];
  foreach (CRM_Contact_Task::$_tasks as $id => $value) {
    if (isset($contactTasks[$id]) && isset($value['url'])) {
      $options['contactTasks'][$id] = $value;
    }
  }
}

/**
 * Expose settings.
 */
function expose_settings(&$options) {
  $options['allowMultipleCaseClients'] = (bool) Civi::settings()->get('civicaseAllowMultipleClients');
  $options['allowCaseLocks'] = (bool) Civi::settings()->get('civicaseAllowCaseLocks');
  $options['defaultCaseCategory'] = strtolower(CRM_Civicase_Helper_CaseCategory::CASE_TYPE_CATEGORY_NAME);
}

/**
 * Retrieve civicase webform url.
 */
function retrieve_civicase_webform_url(&$options) {
  // Retrieve civicase webform URL.
  $allowCaseWebform = Civi::settings()->get('civicaseAllowCaseWebform');
  $options['newCaseWebformUrl'] = $allowCaseWebform ? Civi::settings()->get('civicaseWebformUrl') : NULL;
  $options['newCaseWebformClient'] = 'cid';
  if ($options['newCaseWebformUrl']) {
    $path = explode('/', $options['newCaseWebformUrl']);
    $nid = array_pop($path);
    $client = get_client_delta_from_webform($nid);
    if ($client) {
      $options['newCaseWebformClient'] = 'cid' . $client;
    }
  }
}

return [
  'js' => get_js_files(),
  'css' => [
    // At the moment, it's safe to include this multiple times.
    // deduped by resource manager.
    'assetBuilder://visual-bundle.css',
    'css/*.css',
    'ang/civicase/*.css',
  ],
  'partials' => [
    'ang/civicase',
  ],
  'settings' => $options,
  'requires' => $requires,
  'basePages' => [],
];
