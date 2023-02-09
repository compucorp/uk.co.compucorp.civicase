<?php

/**
 * Manages the option group and values that stores case type category features.
 */
class CRM_Civicase_Setup_Manage_CaseTypeCategoryFeaturesManager extends CRM_Civicase_Setup_Manage_AbstractManager {

  /**
   * Ensures Case Type Category option group and default option values exists.
   *
   * The option values in the option group will store the additional
   * features that can be enabled/disabled for each case type category.
   */
  public function create(): void {
    CRM_Core_BAO_OptionGroup::ensureOptionGroupExists([
      'name' => CRM_Civicase_Service_CaseTypeCategoryFeatures::NAME,
      'title' => ts('Case Type Category Additional Features'),
      'is_reserved' => 1,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => CRM_Civicase_Service_CaseTypeCategoryFeatures::NAME,
      'name' => 'quotations',
      'label' => 'Quotations',
      'is_default' => TRUE,
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => CRM_Civicase_Service_CaseTypeCategoryFeatures::NAME,
      'name' => 'invoices',
      'label' => 'Invoices',
      'is_default' => TRUE,
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => CRM_Civicase_Service_CaseTypeCategoryFeatures::NAME,
      'name' => 'pledges',
      'label' => 'Pledges',
      'is_default' => TRUE,
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);
  }

  /**
   * Removes the entity.
   */
  public function remove(): void {
    civicrm_api3('OptionGroup', 'get', [
      'return' => ['id'],
      'name' => CRM_Civicase_Service_CaseTypeCategoryFeatures::NAME,
      'api.OptionGroup.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * {@inheritDoc}
   */
  protected function toggle($status): void {
    civicrm_api3('OptionGroup', 'get', [
      'sequential' => 1,
      'name' => CRM_Civicase_Service_CaseTypeCategoryFeatures::NAME,
      'api.OptionGroup.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
