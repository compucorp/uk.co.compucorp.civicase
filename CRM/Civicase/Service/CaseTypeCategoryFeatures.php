<?php

use Civi\Api4\OptionGroup;
use Civi\Api4\CaseCategoryFeatures;
use Civi\Api4\OptionValue as OptionValue;

/**
 * CaseTypeCategoryFeatures class.
 */
class CRM_Civicase_Service_CaseTypeCategoryFeatures {

  const NAME = 'case_type_category_features';

  /**
   * Gets the available additional features.
   */
  public function getFeatures() {
    $optionValues = OptionValue::get()
      ->addSelect('id', 'label', 'value', 'name', 'option_group_id')
      ->addWhere('option_group_id:name', '=', self::NAME)
      ->execute();

    return $optionValues;
  }

  /**
   * Retrieves case instance that has the defined features enabled.
   *
   * @param array $features
   *   The features to retrieve case instances for.
   *
   * @return array
   *   Array of Key\Pair value grouped by case instance id.
   */
  public function retrieveCaseInstanceWithEnabledFeatures(array $features) {
    $caseInstanceGroup = OptionGroup::get()->addWhere('name', '=', 'case_type_categories')->execute()[0] ?? NULL;

    if (empty($caseInstanceGroup)) {
      return [];
    }

    $result = CaseCategoryFeatures::get()
      ->addSelect('*', 'option_value.label', 'option_value.name', 'feature_id:name', 'feature_id:label', 'navigation.id')
      ->addJoin('OptionValue AS option_value', 'LEFT',
      ['option_value.value', '=', 'category_id']
    )
      ->addJoin('Navigation AS navigation', 'LEFT',
      ['navigation.name', '=', 'option_value.name']
    )
      ->addWhere('option_value.option_group_id', '=', $caseInstanceGroup['id'])
      ->addWhere('feature_id:name', 'IN', $features)
      ->execute();

    $caseCategoriesGroup = array_reduce((array) $result, function (array $accumulator, array $element) {
      $accumulator[$element['category_id']]['items'][] = $element;
      $accumulator[$element['category_id']]['navigation_id'] = $element['navigation.id'];
      $accumulator[$element['category_id']]['name'] = $element['option_value.name'];

      return $accumulator;
    }, []);

    return $caseCategoriesGroup;
  }

}
