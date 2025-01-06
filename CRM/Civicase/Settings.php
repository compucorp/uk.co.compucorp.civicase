<?php

use CRM_Civicase_Helper_CaseUrl as CaseUrlHelper;
use CRM_Civicase_Helper_NewCaseWebform as NewCaseWebform;
use CRM_Civicase_Helper_OptionValues as OptionValuesHelper;
use CRM_Civicase_Hook_Permissions_ExportCasesAndReports as ExportCasesAndReports;
use CRM_Civicase_Service_CaseCategoryCustomFieldsSetting as CaseCategoryCustomFieldsSetting;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;
use Civi\CCase\Utils as Utils;
use CRM_Civicase_Service_CaseTypeCategoryFeatures as CaseTypeCategoryFeatures;
use Civi\Api4\OptionValue;
use Civi\Utils\CurrencyUtils;

/**
 * Get a list of settings for angular pages.
 */
class CRM_Civicase_Settings {

  /**
   * Get a list of settings for angular pages.
   */
  public static function getAll(): array {
    $options = [
      'activityTypes' => 'activity_type',
      'activityStatuses' => 'activity_status',
      'caseStatuses' => 'case_status',
      'priority' => 'priority',
      'activityCategories' => 'activity_category',
      'caseCategoryInstanceType' => 'case_category_instance_type',
    ];

    [$caseCategoryId, $caseCategoryName] = CaseUrlHelper::getCategoryParamsFromUrl();

    CRM_Civicase_Hook_Helper_CaseTypeCategory::addWordReplacements($caseCategoryName);
    $permissionService = new CaseCategoryPermission();
    $caseCategoryPermissions = $permissionService->get($caseCategoryName);
    $caseCategorySetting = new CRM_Civicase_Service_CaseCategorySetting();

    OptionValuesHelper::setToJsVariables($options);
    NewCaseWebform::addWebformDataToOptions($options, $caseCategorySetting);
    self::setCaseActions($options, $caseCategoryPermissions);
    self::setContactTasks($options);
    self::setCaseTypesToJsVars($options);
    self::setCaseCategoryInstanceToJsVars($options);
    self::setRelationshipTypesToJsVars($options);
    self::setFileCategoriesToJsVars($options);
    self::setActivityStatusTypesToJsVars($options);
    self::setCustomFieldsInfoToJsVars($options);
    self::setTagsToJsVars($options);
    self::addCaseTypeCategoriesToOptions($options);
    self::exposeSettings($options, ['caseCategoryId' => $caseCategoryId]);

    return $options;
  }

  /**
   * Get a list of features settings for angular pages.
   */
  public static function getFeaturesSettings(): array {
    $options = [];

    self::setCurrencyCodes($options);
    self::setCaseTypesWithFeaturesEnabled($options);
    self::setCaseSalesOrderStatus($options);

    return $options;
  }

  /**
   * Bulk actions for case list.
   *
   * We put this here so it can be modified by other extensions.
   */
  public static function setCaseActions(array &$options, array $caseCategoryPermissions): void {
    $options['caseActions'] = [
      [
        'title' => ts('Change Case Status'),
        'action' => 'ChangeStatus',
        'icon' => 'fa-pencil-square-o',
        'is_write_action' => TRUE,
      ],
      [
        'title' => ts('Edit Tags'),
        'action' => 'EditTags',
        'icon' => 'fa-tags',
        'number' => 1,
        'is_write_action' => TRUE,
      ],
      [
        'title' => ts('Print Case'),
        'action' => 'Print',
        'number' => 1,
        'icon' => 'fa-print',
        'is_write_action' => FALSE,
      ],
      [
        'title' => ts('Email - send now'),
        'action' => 'Email',
        'icon' => 'fa-envelope-o',
        'is_write_action' => TRUE,
      ],
      [
        'title' => ts('Print/Merge Document'),
        'action' => 'PrintMerge',
        'icon' => 'fa-file-pdf-o',
        'is_write_action' => TRUE,
      ],
      [
        'title' => ts('Link Cases'),
        'action' => 'LinkCases',
        'number' => 1,
        'icon' => 'fa-link',
        'is_write_action' => TRUE,
      ],
      [
        'title' => ts('Link 2 Cases'),
        'action' => 'LinkCases',
        'number' => 2,
        'icon' => 'fa-link',
        'is_write_action' => TRUE,
      ],
    ];
    if (CRM_Core_Permission::check('administer CiviCase')) {
      $options['caseActions'][] = [
        'title' => ts('Merge 2 Cases'),
        'number' => 2,
        'action' => 'MergeCases',
        'icon' => 'fa-compress',
        'is_write_action' => TRUE,
      ];
      $options['caseActions'][] = [
        'title' => ts('Lock Case'),
        'action' => 'LockCases',
        'number' => 1,
        'icon' => 'fa-lock',
        'is_write_action' => TRUE,
      ];
    }
    if (CRM_Core_Permission::check($caseCategoryPermissions['DELETE_IN_CASE_CATEGORY']['name'])) {
      $options['caseActions'][] = [
        'title' => ts('Delete Case'),
        'action' => 'DeleteCases',
        'icon' => 'fa-trash',
        'is_write_action' => TRUE,
      ];
    }
    if (CRM_Core_Permission::check(ExportCasesAndReports::PERMISSION_NAME)) {
      $options['caseActions'][] = [
        'title' => ts('Export Cases'),
        'action' => 'ExportCases',
        'icon' => 'fa-file-excel-o',
        'is_write_action' => FALSE,
      ];
    }

    self::addWebformsCaseAction($options);
  }

  /**
   * Add webforms with cases attached to menu.
   */
  public static function addWebformsCaseAction(array &$options): void {
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

          $client = NewCaseWebform::getCaseWebformClientId($webform['nid']);

          $items[] = [
            'title' => $webform['title'],
            'action' => 'GoToWebform',
            'path' => $webform['path'],
            'case_type_ids' => $webform['case_type_ids'],
            'clientID' => $client,
            'is_write_action' => FALSE,
          ];
        }
        $options['caseActions'][] = [
          'title' => ts('Webforms'),
          'action' => 'Webforms',
          'icon' => 'fa-file-text-o',
          'items' => $items,
          'is_write_action' => FALSE,
        ];
      }
    }
  }

  /**
   * Sets contact tasks.
   */
  public static function setContactTasks(array &$options): void {
    $contactTasks = CRM_Contact_Task::permissionedTaskTitles(CRM_Core_Permission::getPermission());
    $options['contactTasks'] = [];
    foreach (CRM_Contact_Task::$_tasks as $id => $value) {
      if (isset($contactTasks[$id]) && isset($value['url'])) {
        $options['contactTasks'][$id] = $value;
      }
    }
  }

  /**
   * Sets the case types to javascript global variable.
   */
  public static function setCaseTypesToJsVars(array &$options): void {
    $caseTypes = civicrm_api3('CaseType', 'get', [
      'return' => [
        'id',
        'name',
        'title',
        'description',
        'definition',
        'case_type_category',
        'is_active',
      ],
      'options' => ['limit' => 0, 'sort' => 'weight'],
    ]);
    foreach ($caseTypes['values'] as &$item) {
      CRM_Utils_Array::remove($item, 'is_forkable', 'is_forked');
    }
    $options['caseTypes'] = $caseTypes['values'];
  }

  /**
   * Sets the tags and tagsets to javascript global variable.
   */
  public static function setCaseCategoryInstanceToJsVars(array &$options): void {
    $result = civicrm_api3('CaseCategoryInstance', 'get', [
      'options' => ['limit' => 0],
    ])['values'];
    $options['caseCategoryInstanceMapping'] = $result;
  }

  /**
   * Expose settings.
   *
   * The default case category is taken from URL first,
   * or uses `case` as the default.
   *
   * @param array $options
   *   The options that will store the exposed settings.
   * @param array $defaults
   *   Default values to use when exposing settings.
   */
  public static function exposeSettings(array &$options, array $defaults): void {
    $options['allowMultipleCaseClients'] = (bool) Civi::settings()->get('civicaseAllowMultipleClients');
    $options['showComingSoonCaseSummaryBlock'] = (bool) Civi::settings()->get('civicaseShowComingSoonCaseSummaryBlock');
    $options['allowCaseLocks'] = (bool) Civi::settings()->get('civicaseAllowCaseLocks');
    $options['allowLinkedCasesTab'] = (bool) Civi::settings()->get('civicaseAllowLinkedCasesTab');
    $options['showWebformsListSeparately'] = (bool) Civi::settings()->get('civicaseShowWebformsListSeparately');
    $options['webformsDropdownButtonLabel'] = Civi::settings()->get('civicaseWebformsDropdownButtonLabel');
    $options['showFullContactNameOnActivityFeed'] = (bool) Civi::settings()->get('showFullContactNameOnActivityFeed');
    $options['includeActivitiesForInvolvedContact'] = (bool) Civi::settings()->get('includeActivitiesForInvolvedContact');
    $options['civicaseSingleCaseRolePerType'] = (bool) Civi::settings()->get('civicaseSingleCaseRolePerType');
    $options['caseTypeCategoriesWhereUserCanAccessActivities'] =
      CRM_Civicase_Helper_CaseCategory::getWhereUserCanAccessActivities();
    $options['currentCaseCategory'] = $defaults['caseCategoryId'] ?: NULL;
  }

  /**
   * Sets the relationship types to javascript global variable.
   */
  public static function setRelationshipTypesToJsVars(array &$options): void {
    $result = civicrm_api3('RelationshipType', 'get', [
      'options' => ['limit' => 0],
    ]);
    $options['relationshipTypes'] = $result['values'];
  }

  /**
   * Sets the file categories to javascript global variable.
   */
  public static function setFileCategoriesToJsVars(array &$options): void {
    $options['fileCategories'] = CRM_Civicase_FileCategory::getCategories();
  }

  /**
   * Sets the activity status types to javascript global variable.
   */
  public static function setActivityStatusTypesToJsVars(array &$options): void {
    $options['activityStatusTypes'] = [
      'incomplete' => array_keys(\CRM_Activity_BAO_Activity::getStatusesByType(CRM_Activity_BAO_Activity::INCOMPLETE)),
      'completed' => array_keys(\CRM_Activity_BAO_Activity::getStatusesByType(CRM_Activity_BAO_Activity::COMPLETED)),
      'cancelled' => array_keys(\CRM_Activity_BAO_Activity::getStatusesByType(CRM_Activity_BAO_Activity::CANCELLED)),
    ];
  }

  /**
   * Sets the custom fields information to javascript global variable.
   */
  public static function setCustomFieldsInfoToJsVars(array &$options): void {
    $result = civicrm_api3('CustomGroup', 'get', [
      'sequential' => 1,
      'return' => ['extends_entity_column_value', 'title', 'extends'],
      'extends' => ['IN' => ['Case', 'Activity']],
      'is_active' => 1,
      'options' => ['sort' => 'weight'],
      'api.CustomField.get' => [
        'is_active' => 1,
        'is_searchable' => 1,
        'return' => [
          'label', 'html_type', 'data_type', 'is_search_range',
          'filter', 'option_group_id',
        ],
        'options' => ['sort' => 'weight'],
      ],
    ]);
    $options['customSearchFields'] = $options['customActivityFields'] = [];
    foreach ($result['values'] as $group) {
      if (!empty($group['api.CustomField.get']['values'])) {
        if ($group['extends'] == 'Case') {
          if (!empty($group['extends_entity_column_value'])) {
            $group['caseTypes'] = CRM_Utils_Array::collect('name', array_values(array_intersect_key($options['caseTypes'], array_flip($group['extends_entity_column_value']))));
          }
          foreach ($group['api.CustomField.get']['values'] as $field) {
            $group['fields'][] = Utils::formatCustomSearchField($field);
          }
          unset($group['api.CustomField.get']);
          $options['customSearchFields'][] = $group;
        }
        else {
          foreach ($group['api.CustomField.get']['values'] as $field) {
            $options['customActivityFields'][] = Utils::formatCustomSearchField($field) + ['group' => $group['title']];
          }
        }
      }
    }
  }

  /**
   * Sets the tags and tagsets to javascript global variable.
   */
  public static function setTagsToJsVars(array &$options): void {
    $options['tags'] = CRM_Core_BAO_Tag::getColorTags('civicrm_case');
    $options['tagsets'] = CRM_Utils_Array::value('values', civicrm_api3('Tag', 'get', [
      'sequential' => 1,
      'return' => ["id", "name"],
      'used_for' => ['LIKE' => "%civicrm_case%"],
      'is_tagset' => 1,
    ]));
  }

  /**
   * Adds the case type categories and their labels to the given options.
   *
   * @param array $options
   *   List of options to pass to the front-end.
   */
  public static function addCaseTypeCategoriesToOptions(array &$options): void {
    $caseCategoryCustomFields = new CaseCategoryCustomFieldsSetting();
    $caseCategories = civicrm_api3('OptionValue', 'get', [
      'is_sequential' => '1',
      'option_group_id' => 'case_type_categories',
      'options' => ['limit' => 0],
    ]);

    foreach ($caseCategories['values'] as &$caseCategory) {
      $caseCategory['custom_fields'] = $caseCategoryCustomFields->get(
        $caseCategory['value']
      );
    }

    $options['caseTypeCategories'] = array_column($caseCategories['values'], NULL, 'value');
  }

  /**
   * Exposes currency codes to Angular.
   *
   * @param array $options
   *   List of options to pass to the front-end.
   */
  public static function setCurrencyCodes(array &$options): void {
    $options['currencyCodes'] = CurrencyUtils::getCurrencies();
  }

  /**
   * Exposes Case types that have features enabled to Angular.
   *
   * @param array $options
   *   List of options to pass to the front-end.
   */
  public static function setCaseTypesWithFeaturesEnabled(array &$options): void {
    $caseTypeCategoryFeatures = new CaseTypeCategoryFeatures();

    array_map(function ($feature) use ($caseTypeCategoryFeatures, &$options) {
      $caseTypeCategories = $caseTypeCategoryFeatures->retrieveCaseInstanceWithEnabledFeatures([$feature]);
      $options['featureCaseTypes'][$feature] = array_keys($caseTypeCategories);
    }, ['quotations', 'invoices']);
  }

  /**
   * Exposes case sales order statuses to Angular.
   *
   * @param array $options
   *   List of options to pass to the front-end.
   */
  public static function setCaseSalesOrderStatus(array &$options): void {
    $optionValues = OptionValue::get(FALSE)
      ->addSelect('id', 'value', 'name', 'label')
      ->addWhere('option_group_id:name', '=', 'case_sales_order_status')
      ->execute();

    $options['salesOrderStatus'] = $optionValues->getArrayCopy();
  }

}
