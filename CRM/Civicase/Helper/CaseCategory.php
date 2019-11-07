<?php

use CRM_Case_BAO_CaseType as CaseType;

/**
 * CRM_Civicase_Helper_CaseCategory class.
 */
class CRM_Civicase_Helper_CaseCategory {

  const CASE_TYPE_CATEGORY_NAME = 'Cases';

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

    $result = civicrm_api3('Case', 'getsingle', [
      'id' => $caseId,
      'return' => ['case_type_id.case_type_category'],
    ]);

    if (!empty($result['case_type_id.case_type_category'])) {
      $caseCategoryId = $result['case_type_id.case_type_category'];

      return $caseTypeCategories[$caseCategoryId];
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
   * Returns the case types for the cases category.
   *
   * @return array
   *   Array of Case Types indexed by Id.
   */
  public static function getCaseTypesForCase() {
    $result = civicrm_api3('CaseType', 'get', [
      'sequential' => 1,
      'return' => ['title', 'id'],
      'case_type_category' => self::CASE_TYPE_CATEGORY_NAME,
    ]);

    return array_column($result['values'], 'title', 'id');
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
    $optionName = $caseTypeCategoryName . "_word_replacement";
    try {
      $result = civicrm_api3('OptionValue', 'getsingle', [
        'option_group_id' => 'case_type_category_word_replacement_class',
        'name' => $optionName,
      ]);

    } catch (Exception $e) {
      return [];
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

}
