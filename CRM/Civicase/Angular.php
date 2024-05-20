<?php

/**
 * Option factory class for Civicase AngularJS module
 */
class CRM_Civicase_Angular {

  private static $options = [];
  private static $case_category_permissions;

  /**
   * @return array
   * @throws \Exception
   */
  public static function getOptions(): array {
    self::load_resources();
    [
      $caseCategoryId,
      $caseCategoryName,
    ] = CRM_Civicase_Helper_CaseUrl::getCategoryParamsFromUrl();

    // Word replacements are already loaded for the contact tab ContactCaseTab.
    if (CRM_Utils_System::currentPath() !== 'civicrm/case/contact-case-tab') {
      $notTranslationPath = $caseCategoryName == CRM_Civicase_Helper_CaseCategory::CASE_TYPE_CATEGORY_NAME && CRM_Utils_System::currentPath() != 'civicrm/case/a';

      if (!$notTranslationPath) {
        if (!in_array($caseCategoryName, CRM_Civicase_Helper_CaseCategory::getAccessibleCaseTypeCategories())) {
          throw new Exception('Access denied! You are not authorized to access this page.');
        }

        CRM_Civicase_Hook_Helper_CaseTypeCategory::addWordReplacements($caseCategoryName);
      }
    }

    $permissionService               = new \CRM_Civicase_Service_CaseCategoryPermission();
    self::$case_category_permissions = $permissionService->get($caseCategoryName);

    // The following changes are only relevant to the full-page app.
    if (CRM_Utils_System::currentPath() == 'civicrm/case/a') {
      self::adds_shoreditch_css();
      \CRM_Civicase_Helper_CaseCategory::updateBreadcrumbs($caseCategoryId);
    }

    self::set_case_actions();
    self::set_contact_tasks();

    return self::$options;
  }

  /**
   * Loads Resources.
   */
  public static function load_resources() {
    Civi::resources()
      ->addPermissions([
        'administer CiviCase',
        'administer CiviCRM',
        'access all cases and activities',
        'add cases',
        'basic case information',
        'access CiviCRM',
        'access my cases and activities',
      ])
      ->addScriptFile('org.civicrm.shoreditch', 'base/js/affix.js', 1000, 'html-header')
      ->addSetting([
        'config' => [
          'enableComponents' => CRM_Core_Config::singleton()->enableComponents,
          'user_contact_id'  => (int) CRM_Core_Session::getLoggedInContactID(),
        ],
      ]);
  }

  /**
   * Add shoreditch custom css if not already present.
   */
  public static function adds_shoreditch_css() {
    if (!civicrm_api3('Setting', 'getvalue', ['name' => "customCSSURL"])) {
      Civi::resources()
        ->addStyleFile('org.civicrm.shoreditch', 'css/custom-civicrm.css', 99, 'html-header');
    }
  }

  /**
   * Get a list of JS files.
   */
  public static function get_js_files() {
    return array_merge(
      [
        // At the moment, it's safe to include this multiple times.
        // deduped by resource manager.
        'assetBuilder://visual-bundle.js',
        'ang/civicase.js',
      ],
      CRM_Civicase_Helper_GlobRecursive::getRelativeToExtension(
        'uk.co.compucorp.civicase',
        'ang/civicase/*.js'
    )
    );
  }

  /**
   * Bulk actions for case list.
   *
   * We put this here so it can be modified by other extensions.
   */
  public static function set_case_actions() {
    self::$options['caseActions'] = [
      [
        'title'           => ts('Change Case Status'),
        'action'          => 'ChangeStatus',
        'icon'            => 'fa-pencil-square-o',
        'is_write_action' => TRUE,
      ],
      [
        'title'           => ts('Edit Tags'),
        'action'          => 'EditTags',
        'icon'            => 'fa-tags',
        'number'          => 1,
        'is_write_action' => TRUE,
      ],
      [
        'title'           => ts('Print Case'),
        'action'          => 'Print',
        'number'          => 1,
        'icon'            => 'fa-print',
        'is_write_action' => FALSE,
      ],
      [
        'title'           => ts('Email - send now'),
        'action'          => 'Email',
        'icon'            => 'fa-envelope-o',
        'is_write_action' => TRUE,
      ],
      [
        'title'           => ts('Print/Merge Document'),
        'action'          => 'PrintMerge',
        'icon'            => 'fa-file-pdf-o',
        'is_write_action' => TRUE,
      ],
      [
        'title'           => ts('Link Cases'),
        'action'          => 'LinkCases',
        'number'          => 1,
        'icon'            => 'fa-link',
        'is_write_action' => TRUE,
      ],
      [
        'title'           => ts('Link 2 Cases'),
        'action'          => 'LinkCases',
        'number'          => 2,
        'icon'            => 'fa-link',
        'is_write_action' => TRUE,
      ],
    ];
    if (CRM_Core_Permission::check('administer CiviCase')) {
      self::$options['caseActions'][] = [
        'title'           => ts('Merge 2 Cases'),
        'number'          => 2,
        'action'          => 'MergeCases',
        'icon'            => 'fa-compress',
        'is_write_action' => TRUE,
      ];
      self::$options['caseActions'][] = [
        'title'           => ts('Lock Case'),
        'action'          => 'LockCases',
        'number'          => 1,
        'icon'            => 'fa-lock',
        'is_write_action' => TRUE,
      ];
    }
    if (CRM_Core_Permission::check(self::$case_category_permissions['DELETE_IN_CASE_CATEGORY']['name'])) {
      self::$options['caseActions'][] = [
        'title'           => ts('Delete Case'),
        'action'          => 'DeleteCases',
        'icon'            => 'fa-trash',
        'is_write_action' => TRUE,
      ];
    }
    if (CRM_Core_Permission::check(CRM_Civicase_Hook_Permissions_ExportCasesAndReports::PERMISSION_NAME)) {
      self::$options['caseActions'][] = [
        'title'           => ts('Export Cases'),
        'action'          => 'ExportCases',
        'icon'            => 'fa-file-excel-o',
        'is_write_action' => FALSE,
      ];
    }

    self::add_webforms_case_action();
  }

  /**
   * Add webforms with cases attached to menu.
   */
  public static function add_webforms_case_action() {
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

          $items[] = [
            'title'           => $webform['title'],
            'action'          => 'GoToWebform',
            'path'            => $webform['path'],
            'case_type_ids'   => $webform['case_type_ids'],
            'clientID'        => NULL,
            'is_write_action' => FALSE,
          ];
        }
        self::$options['caseActions'][] = [
          'title'           => ts('Forms'),
          'action'          => 'Forms',
          'icon'            => 'fa-file-text-o',
          'items'           => $items,
          'is_write_action' => FALSE,
        ];
      }
    }
  }

  /**
   * Sets contact tasks.
   */
  public static function set_contact_tasks() {
    $contactTasks = CRM_Contact_Task::permissionedTaskTitles(CRM_Core_Permission::getPermission());
    self::$options['contactTasks'] = [];
    foreach (CRM_Contact_Task::$_tasks as $id => $value) {
      if (isset($contactTasks[$id]) && isset($value['url'])) {
        self::$options['contactTasks'][$id] = $value;
      }
    }
  }

}
