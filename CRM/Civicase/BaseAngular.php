<?php

use Civi\CCase\Utils;

/**
 * Option factory class for Civicase base AngularJS module
 */
class CRM_Civicase_BaseAngular {

  private static $options;

  /**
   * @return string[]
   */
  public static function getOptions(): array {
    [
      $caseCategoryId,
      $caseCategoryName,
    ] = CRM_Civicase_Helper_CaseUrl::getCategoryParamsFromUrl();

    $caseCategorySetting = new CRM_Civicase_Service_CaseCategorySetting();

    self::$options = [
      'activityTypes'            => 'activity_type',
      'activityStatuses'         => 'activity_status',
      'caseStatuses'             => 'case_status',
      'priority'                 => 'priority',
      'activityCategories'       => 'activity_category',
      'caseCategoryInstanceType' => 'case_category_instance_type',
    ];

    CRM_Civicase_Helper_OptionValues::setToJsVariables(self::$options);
    CRM_Civicase_Helper_NewCaseWebform::addWebformDataToOptions(self::$options, $caseCategorySetting);
    self::set_case_types_to_js_vars();
    self::set_case_category_instance_to_js_vars();
    self::set_relationship_types_to_js_vars();
    self::set_file_categories_to_js_vars();
    self::set_activity_status_types_to_js_vars();
    self::set_custom_fields_info_to_js_vars();
    self::set_tags_to_js_vars();
    self::add_case_type_categories_to_options();
    self::expose_settings([
      'caseCategoryId' => $caseCategoryId,
    ]);

    return self::$options;
  }

  /**
   * Adds the case type categories and their labels to the given options.
   *
   *   List of options to pass to the front-end.
   */
  public static function add_case_type_categories_to_options() {
    $caseCategoryCustomFields = new CRM_Civicase_Service_CaseCategoryCustomFieldsSetting();
    $caseCategories           = civicrm_api3('OptionValue', 'get', [
      'is_sequential'   => '1',
      'option_group_id' => 'case_type_categories',
      'options'         => ['limit' => 0],
    ]);

    foreach ($caseCategories['values'] as &$caseCategory) {
      $caseCategory['custom_fields'] = $caseCategoryCustomFields->get(
      $caseCategory['value']
      );
    }

    self::$options['caseTypeCategories'] = array_column($caseCategories['values'], NULL, 'value');
  }

  /**
   * Sets the tags and tagsets to javascript global variable.
   */
  public static function set_case_category_instance_to_js_vars() {
    $result                                       = civicrm_api3('CaseCategoryInstance', 'get', [
      'options' => ['limit' => 0],
    ])['values'];
    self::$options['caseCategoryInstanceMapping'] = $result;
  }

  /**
   * Expose settings.
   *
   * The default case category is taken from URL first,
   * or uses `case` as the default.
   *
   *   The options that will store the exposed settings.
   *
   * @param array $defaults
   *   Default values to use when exposing settings.
   */
  public static function expose_settings(array $defaults) {
    self::$options['allowMultipleCaseClients']                       = (bool) Civi::settings()
      ->get('civicaseAllowMultipleClients');
    self::$options['showComingSoonCaseSummaryBlock']                 = (bool) Civi::settings()
      ->get('civicaseShowComingSoonCaseSummaryBlock');
    self::$options['allowCaseLocks']                                 = (bool) Civi::settings()
      ->get('civicaseAllowCaseLocks');
    self::$options['allowLinkedCasesTab']                            = (bool) Civi::settings()
      ->get('civicaseAllowLinkedCasesTab');
    self::$options['showWebformsListSeparately']                     = (bool) Civi::settings()
      ->get('civicaseShowWebformsListSeparately');
    self::$options['webformsDropdownButtonLabel']                    = Civi::settings()
      ->get('civicaseWebformsDropdownButtonLabel');
    self::$options['showFullContactNameOnActivityFeed']              = (bool) Civi::settings()
      ->get('showFullContactNameOnActivityFeed');
    self::$options['includeActivitiesForInvolvedContact']            = (bool) Civi::settings()
      ->get('includeActivitiesForInvolvedContact');
    self::$options['civicaseSingleCaseRolePerType']                  = (bool) Civi::settings()
      ->get('civicaseSingleCaseRolePerType');
    self::$options['caseTypeCategoriesWhereUserCanAccessActivities'] =
    CRM_Civicase_Helper_CaseCategory::getWhereUserCanAccessActivities();
    self::$options['currentCaseCategory']                            = $defaults['caseCategoryId'] ?: NULL;
  }

  /**
   * Sets the case types to javascript global variable.
   */
  public static function set_case_types_to_js_vars() {
    $caseTypes = civicrm_api3('CaseType', 'get', [
      'return'  => [
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
    self::$options['caseTypes'] = $caseTypes['values'];
  }

  /**
   * Sets the relationship types to javascript global variable.
   */
  public static function set_relationship_types_to_js_vars() {
    $result                             = civicrm_api3('RelationshipType', 'get', [
      'options' => ['limit' => 0],
    ]);
    self::$options['relationshipTypes'] = $result['values'];
  }

  /**
   * Sets the tags and tagsets to javascript global variable.
   */
  public static function set_tags_to_js_vars() {
    self::$options['tags']    = CRM_Core_BAO_Tag::getColorTags('civicrm_case');
    self::$options['tagsets'] = CRM_Utils_Array::value('values', civicrm_api3('Tag', 'get', [
      'sequential' => 1,
      'return'     => ["id", "name"],
      'used_for'   => ['LIKE' => "%civicrm_case%"],
      'is_tagset'  => 1,
    ]));
  }

  /**
   * Sets the file categories to javascript global variable.
   */
  public static function set_file_categories_to_js_vars() {
    self::$options['fileCategories'] = CRM_Civicase_FileCategory::getCategories();
  }

  /**
   * Sets the activity status types to javascript global variable.
   */
  public static function set_activity_status_types_to_js_vars() {
    self::$options['activityStatusTypes'] = [
      'incomplete' => array_keys(CRM_Activity_BAO_Activity::getStatusesByType(CRM_Activity_BAO_Activity::INCOMPLETE)),
      'completed'  => array_keys(CRM_Activity_BAO_Activity::getStatusesByType(CRM_Activity_BAO_Activity::COMPLETED)),
      'cancelled'  => array_keys(CRM_Activity_BAO_Activity::getStatusesByType(CRM_Activity_BAO_Activity::CANCELLED)),
    ];
  }

  /**
   * Sets the custom fields information to javascript global variable.
   */
  public static function set_custom_fields_info_to_js_vars() {
    $result                              = civicrm_api3('CustomGroup', 'get', [
      'sequential'          => 1,
      'return'              => [
        'extends_entity_column_value',
        'title',
        'extends',
      ],
      'extends'             => ['IN' => ['Case', 'Activity']],
      'is_active'           => 1,
      'options'             => ['sort' => 'weight'],
      'api.CustomField.get' => [
        'is_active'     => 1,
        'is_searchable' => 1,
        'return'        => [
          'label',
          'html_type',
          'data_type',
          'is_search_range',
          'filter',
          'option_group_id',
        ],
        'options'       => ['sort' => 'weight'],
      ],
    ]);
    self::$options['customSearchFields'] = self::$options['customActivityFields'] = [];
    foreach ($result['values'] as $group) {
      if (!empty($group['api.CustomField.get']['values'])) {
        if ($group['extends'] == 'Case') {
          if (!empty($group['extends_entity_column_value'])) {
            $group['caseTypes'] = CRM_Utils_Array::collect('name', array_values(array_intersect_key(self::$options['caseTypes'], array_flip($group['extends_entity_column_value']))));
          }
          foreach ($group['api.CustomField.get']['values'] as $field) {
            $group['fields'][] = Utils::formatCustomSearchField($field);
          }
          unset($group['api.CustomField.get']);
          self::$options['customSearchFields'][] = $group;
        }
        else {
          foreach ($group['api.CustomField.get']['values'] as $field) {
            self::$options['customActivityFields'][] = Utils::formatCustomSearchField($field) + ['group' => $group['title']];
          }
        }
      }
    }
  }

  /**
   * Get a list of JS files.
   *
   * @return array
   *   list of js files
   */
  public static function get_js_files() {
    return array_merge(
    [
      'assetBuilder://visual-bundle.js',
      'ang/civicase-base.js',
    ],
    CRM_Civicase_Helper_GlobRecursive::getRelativeToExtension(
                'uk.co.compucorp.civicase',
                'ang/civicase-base/*.js'
    )
    );
  }

}
