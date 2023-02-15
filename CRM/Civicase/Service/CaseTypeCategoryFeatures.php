<?php

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

}
