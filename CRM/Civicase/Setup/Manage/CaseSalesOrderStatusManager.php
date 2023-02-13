<?php

/**
 * Manages the option group and values that stores sales order statuses.
 */
class CRM_Civicase_Setup_Manage_CaseSalesOrderStatusManager extends CRM_Civicase_Setup_Manage_AbstractManager {

  const NAME = 'case_sales_order_status';

  /**
   * Ensures Sales Order Status option group and default option values exists.
   *
   * The option values in the option group will store the available statuses
   * for sales order.
   */
  public function create(): void {
    CRM_Core_BAO_OptionGroup::ensureOptionGroupExists([
      'name' => self::NAME,
      'title' => ts('Sales Order Status'),
      'is_reserved' => 1,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::NAME,
      'name' => 'new',
      'label' => 'New',
      'is_default' => TRUE,
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::NAME,
      'name' => 'sent_to_client',
      'label' => 'Sent to client',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::NAME,
      'name' => 'accepted',
      'label' => 'Accepted',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::NAME,
      'name' => 'declined',
      'label' => 'Declined',
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
