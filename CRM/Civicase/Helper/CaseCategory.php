<?php

use CRM_Case_BAO_CaseType as CaseType;
use CRM_Civicase_BAO_CaseCategoryInstance as CaseCategoryInstance;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;
use CRM_Civicase_Service_CaseManagementUtils as CaseManagementUtils;

/**
 * CaseCategory Helper class with useful functions for managing case categories.
 */
class CRM_Civicase_Helper_CaseCategory {

  const CASE_TYPE_CATEGORY_GROUP_NAME = 'case_type_categories';


  /**
   * Case category name.
   */
  const CASE_TYPE_CATEGORY_NAME = 'Cases';

  /**
   * Case category label.
   */
  const CASE_TYPE_CATEGORY_LABEL = 'Cases';

  /**
   * Case category singular label.
   */
  const CASE_TYPE_CATEGORY_SINGULAR_LABEL = 'Case';

  /**
   * Returns the full list of case type categories.
   *
   * @return array
   *   a list of case categories as returned by the option value API.
   */
  public static function getCaseCategories() {
    $result = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => self::CASE_TYPE_CATEGORY_GROUP_NAME,
      'options' => [
        'cache' => TRUE,
      ],
    ]);

    return $result['values'];
  }

  /**
   * Returns the case category name for the case Id.
   *
   * @param int $caseId
   *   The Case ID.
   *
   * @return string|null
   *   The Case Category Name.
   */
  public static function getCategoryName($caseId) {
    $caseTypeCategories = CaseType::buildOptions('case_type_category', 'validate');
    try {
      $result = civicrm_api3('Case', 'getsingle', [
        'id' => $caseId,
        'return' => ['case_type_id.case_type_category'],
      ]);

      if (!empty($result['case_type_id.case_type_category'])) {
        $caseCategoryId = $result['case_type_id.case_type_category'];

        return $caseTypeCategories[$caseCategoryId];
      }
    }
    catch (Exception $e) {
      return NULL;
    }

    return NULL;
  }

  /**
   * Returns the case category name for the case typeId.
   *
   * @param int $caseTypeId
   *   The Case Type ID.
   *
   * @return string|null
   *   The Case Category Name.
   */
  public static function getCategoryNameForCaseType($caseTypeId) {
    $caseTypeCategories = CaseType::buildOptions('case_type_category');
    try {
      $result = civicrm_api3('CaseType', 'getvalue', [
        'id' => $caseTypeId,
        'return' => 'case_type_category',
      ]);

      if (!empty($result['is_error'])) {
        $caseCategoryId = $result['result'];

        return $caseTypeCategories[$caseCategoryId];
      }
    }
    catch (Exception $e) {
      return NULL;
    }

    return NULL;
  }

  /**
   * Returns the case type category name given the option value.
   *
   * @param mixed $caseCategoryValue
   *   Category Option value.
   *
   * @return string
   *   Case Category Name.
   */
  public static function getCaseCategoryNameFromOptionValue($caseCategoryValue) {
    $caseTypeCategories = CaseType::buildOptions('case_type_category', 'validate');

    return $caseTypeCategories[$caseCategoryValue];
  }

  /**
   * Returns the case type category word replacements.
   *
   * @param string $caseTypeCategoryName
   *   Case Category Name.
   *
   * @return array
   *   The word to be replaced and replacement array.
   */
  public static function getWordReplacements($caseTypeCategoryName) {
    $instanceName = self::getInstanceName($caseTypeCategoryName);
    $optionName = $instanceName . '_word_replacement';
    try {
      $result = civicrm_api3('OptionValue', 'getsingle', [
        'option_group_id' => 'case_type_category_word_replacement_class',
        'name' => $optionName,
        'options' => ['cache' => TRUE],
      ]);

    }
    catch (Exception $e) {
      if (!$caseTypeCategoryName || strtolower($caseTypeCategoryName) == 'cases') {
        return [];
      }

      $category = CRM_Civicase_Helper_Category::get($caseTypeCategoryName);

      return [
        'Cases' => '_PLURAL_WILDCARD_',
        'Case' => '_SINGULAR_WILDCARD_',
        '_PLURAL_WILDCARD_' => ucfirst($category['label']),
        '_SINGULAR_WILDCARD_' => ucfirst($category['singular_label']),
        'cases' => '_plural_wildcard_',
        'case' => '_singular_wildcard_',
        '_plural_wildcard_' => strtolower($category['label']),
        '_singular_wildcard_' => strtolower($category['singular_label']),
      ];
    }

    if (empty($result['id'])) {
      return [];
    }

    $replacementClass = $result['value'];
    if (class_exists($replacementClass) && isset(class_implements($replacementClass)[CRM_Civicase_WordReplacement_BaseInterface::class])) {
      $replacements = new $replacementClass();

      return $replacements->get();
    }

    return [];
  }

  /**
   * Gets counts of cases per case-type category for a given contact.
   *
   * Implements:
   *   1. Per-request static cache.
   *   2. 15-minute shared cache via Civi::cache('short').
   *
   * @param int $contactId
   *   The CiviCRM contact ID.
   * @param bool $forceRefresh
   *   If TRUE, ignore all caches and re-run the query.
   *
   * @return int[]
   *   Associative array [ category_name => case_count, … ].
   */
  public static function getAllCaseCounts(int $contactId, bool $forceRefresh = FALSE): array {
    // Use In-memory per-request cache.
    static $requestCache = [];
    if (!$forceRefresh && isset($requestCache[$contactId])) {
      return $requestCache[$contactId];
    }

    // Tier 2 - Shared cache (short bin, 15-minute TTL).
    $cacheKey = "civicase_case_counts_{$contactId}";
    $cache    = \Civi::cache('short');
    if (!$forceRefresh) {
      $cached = $cache->get($cacheKey);
      if ($cached !== NULL) {
        return $requestCache[$contactId] = $cached;
      }
    }

    $sql = "
      SELECT
        ov.name      AS category_name,
        COUNT(cc.id) AS case_count
      FROM civicrm_case_contact cc
      INNER JOIN civicrm_case        c  ON c.id              = cc.case_id
      INNER JOIN civicrm_case_type   ct ON ct.id             = c.case_type_id
      INNER JOIN civicrm_option_group og ON og.name         = 'case_type_categories'
      INNER JOIN civicrm_option_value ov ON ov.option_group_id = og.id
                                         AND ov.value          = ct.case_type_category
                                         AND ov.is_active      = 1
      WHERE cc.contact_id = %1
        AND c.is_deleted = 0
      GROUP BY ov.name
      ORDER BY ov.name
    ";

    $dao = CRM_Core_DAO::executeQuery($sql, [
      1 => [$contactId, 'Integer'],
    ]);

    $counts = [];
    while ($dao->fetch()) {
      $counts[$dao->category_name] = (int) $dao->case_count;
    }

    // TTL = 900s = 15min.
    $cache->set($cacheKey, $counts, 900);

    return $requestCache[$contactId] = $counts;
  }

  /**
   * Return case count for contact for a case category.
   *
   * @param string $caseTypeCategoryName
   *   Case category name.
   * @param int $contactId
   *   Contact ID.
   *
   * @return int
   *   Case count.
   */
  public static function getCaseCount($caseTypeCategoryName, $contactId) {
    $caseCounts = self::getAllCaseCounts($contactId);

    // O(1) lookup.
    return $caseCounts[$caseTypeCategoryName] ?? 0;
  }

  /**
   * Returns the actual case category name stored in case type category.
   *
   * The case type category parameter passed in the URL may have been changed
   * by user e.g might have been changed to upper case or all lower case while
   * the actual value stored in db might be different. This might cause issues
   * because Core uses some function to check if the case category value
   * (especially for custom field) extends match what is stored in some option
   * values array, if these don't match, it might cause an issue in the
   * application behaviour.
   *
   * @param string $caseCategoryNameFromUrl
   *   Case category name passed from URL.
   *
   * @return string
   *   Actual case category name.
   */
  public static function getActualCaseCategoryName($caseCategoryNameFromUrl) {
    $caseCategoryNameFromUrl = strtolower($caseCategoryNameFromUrl);
    $caseTypeCategories = CaseType::buildOptions('case_type_category', 'validate');
    $caseTypeCategoriesCombined = array_combine($caseTypeCategories, $caseTypeCategories);
    $caseTypeCategoriesLower = array_change_key_case($caseTypeCategoriesCombined);

    return !empty($caseTypeCategoriesLower[$caseCategoryNameFromUrl]) ? $caseTypeCategoriesLower[$caseCategoryNameFromUrl] : NULL;

  }

  /**
   * Returns the Case Type Categories that the user has access to.
   *
   * If the Contact Id is not passed, the logged in contact ID is used.
   *
   * @param int|null $contactId
   *   The contact Id to check for.
   *
   * @return array
   *   Case category access.
   */
  public static function getAccessibleCaseTypeCategories($contactId = NULL) {
    $caseTypeCategories = CaseType::buildOptions('case_type_category', 'validate');
    $caseCategoryPermission = new CaseCategoryPermission();
    $permissionToCheck = 'basic case information';
    $caseCategoryAccess = [];
    $contactId = $contactId ? $contactId : CRM_Core_Session::getLoggedInContactID();

    foreach ($caseTypeCategories as $id => $caseTypeCategoryName) {
      $permission = $caseCategoryPermission->replaceWords($permissionToCheck, $caseTypeCategoryName);
      if (CRM_Core_Permission::check($permission, $contactId)) {
        $caseCategoryAccess[$id] = $caseTypeCategoryName;
      }
    }

    return $caseCategoryAccess;
  }

  /**
   * Returns the Case Type Categories the user can access the activities for.
   *
   * If the Contact Id is not passed, the logged in contact ID is used.
   *
   * @param int|null $contactId
   *   The contact Id to check for.
   *
   * @return array
   *   Case category access.
   */
  public static function getWhereUserCanAccessActivities($contactId = NULL) {
    $caseTypeCategories = CaseType::buildOptions('case_type_category', 'validate');
    $caseCategoryPermission = new CaseCategoryPermission();
    $permissionsToCheck = [
      'access my cases and activities',
      'access all cases and activities',
    ];
    $caseCategoryAccess = [];
    $contactId = $contactId ? $contactId : CRM_Core_Session::getLoggedInContactID();
    foreach ($caseTypeCategories as $id => $caseTypeCategoryName) {
      foreach ($permissionsToCheck as $permissionToCheck) {
        $permission = $caseCategoryPermission->replaceWords($permissionToCheck, $caseTypeCategoryName);

        if (CRM_Core_Permission::check($permission, $contactId)) {
          array_push($caseCategoryAccess, $caseTypeCategoryName);

          continue 2;
        }
      }
    }

    return $caseCategoryAccess;
  }

  /**
   * Updates breadcrumb for a case category page.
   *
   * The breadcrumb is updated so that the necessary words can be replaced and
   * also so that the dashboard leads to the case category dashboard page
   * depending on the case category.
   *
   * @param string $caseCategoryId
   *   Case category Id.
   */
  public static function updateBreadcrumbs($caseCategoryId) {
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
        'url' => CRM_Utils_System::url('civicrm/case/a/', ['case_type_category' => $caseCategoryId], TRUE,
          "/case?case_type_category={$caseCategoryId}"),
      ],
    ];

    CRM_Utils_System::appendBreadCrumb($breadcrumb);
  }

  /**
   * Gets the Instance utility object for the case category.
   *
   * We are using the BAO here to fetch the instance value because the API
   * will return an error if the option value has been deleted and will treat
   * the category value as a non valid value. This issue will be observed for
   * the case category delete event.
   *
   * @param string $caseCategoryValue
   *   Case category value.
   *
   * @return \CRM_Civicase_Service_CaseCategoryInstanceUtils
   *   Instance utitlity object.
   */
  public static function getInstanceObject($caseCategoryValue) {
    $caseCategoryInstance = new CRM_Civicase_BAO_CaseCategoryInstance();
    $caseCategoryInstance->category_id = $caseCategoryValue;
    $caseCategoryInstance->find(TRUE);
    $instanceValue = $caseCategoryInstance->instance_id;

    if (!$instanceValue) {
      return new CaseManagementUtils();
    }

    $instanceClasses = CRM_Core_OptionGroup::values('case_category_instance_type', TRUE, TRUE, TRUE, NULL);
    $instanceClass = $instanceClasses[$instanceValue];

    return new $instanceClass($caseCategoryInstance->id);
  }

  /**
   * Returns the Category Instance for the Case category.
   *
   * @param string $caseCategoryName
   *   Case category Name.
   */
  public static function getInstanceName($caseCategoryName) {
    try {
      $result = civicrm_api3('CaseCategoryInstance', 'getsingle', [
        'category_id' => $caseCategoryName,
      ]);
    }
    catch (Exception $e) {
      return NULL;
    }

    $instances = CaseCategoryInstance::buildOptions('instance_id', 'validate');

    return $instances[$result['instance_id']];
  }

  /**
   * Get the weight value of the Cases navigation menu.
   *
   * @return int
   *   Weight of the menu.
   */
  public static function getWeightOfCasesMenu() {
    return CRM_Core_DAO::getFieldValue(
      'CRM_Core_DAO_Navigation',
      'Cases',
      'weight',
      'name'
    );
  }

  /**
   * Gets case category option details by id.
   *
   * @param int $id
   *   Category Id.
   *
   * @return array
   *   Category details.
   */
  public static function getById($id) {
    return self::getByParams(['id' => $id]);
  }

  /**
   * Gets case category option details by name.
   *
   * @param string $name
   *   Category name.
   *
   * @return array
   *   Category details.
   */
  public static function getByName($name) {
    return self::getByParams(['name' => $name]);
  }

  /**
   * Gets case category option details by params.
   *
   * @param array $params
   *   Catetgory params.
   *
   * @return array
   *   Category details.
   */
  private static function getByParams(array $params) {
    $apiParams = [
      'sequential' => 1,
      'option_group_id' => 'case_type_categories',
      'options' => ['cache' => TRUE],
    ];
    $apiParams = array_merge($apiParams, $params);
    $result = civicrm_api3('OptionValue', 'get', $apiParams);

    return !empty($result['values'][0]) ? $result['values'][0] : [];
  }

  /**
   * Returns the option value of Cases category.
   *
   * @return int|null
   *   Case category value.
   */
  public static function getOptionValue() {
    $result = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => 'case_type_categories',
      'name' => self::CASE_TYPE_CATEGORY_NAME,
      'options' => ['cache' => TRUE],
      'return' => ['value'],
    ]);

    if ($result['count'] === 0) {
      return NULL;
    }

    return $result['values'][0]['value'];
  }

  /**
   * Get data for creating the menu.
   *
   * @return string[]
   *   Category data.
   */
  public static function getDataForMenu() {
    return [
      'name' => self::CASE_TYPE_CATEGORY_NAME,
      'label' => self::CASE_TYPE_CATEGORY_LABEL,
      'singular_label' => self::CASE_TYPE_CATEGORY_SINGULAR_LABEL,
    ];
  }

}
