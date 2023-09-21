<?php

/**
 * Manages the option group and values that stores sales order statuses.
 */
class CRM_Civicase_Setup_Manage_CaseSalesOrderStatusManager extends CRM_Civicase_Setup_Manage_AbstractManager {

  const SALE_ORDER_STATUS_NAME = 'case_sales_order_status';
  const SALE_ORDER_INVOICING_STATUS_NANE = 'case_sales_order_invoicing_status';
  const SALE_ORDER_PAYMENT_STATUS_NANE = 'case_sales_order_payment_status';

  /**
   * Ensures Sales Order Status option group and default option values exists.
   *
   * The option values in the option group will store the available statuses
   * for sales order.
   */
  public function create(): void {
    $this->createSaleOrderStatus();
    $this->createSaleOrderInvoicingStatus();
    $this->createSaleOrderPaymentStatus();
  }

  /**
   * Creates sale order status.
   */
  private function createSaleOrderStatus() {
    CRM_Core_BAO_OptionGroup::ensureOptionGroupExists([
      'name' => self::SALE_ORDER_STATUS_NAME,
      'title' => ts('Sales Order Status'),
      'is_reserved' => 1,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_STATUS_NAME,
      'name' => 'new',
      'label' => 'New',
      'is_default' => TRUE,
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_STATUS_NAME,
      'name' => 'sent_to_client',
      'label' => 'Sent to client',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_STATUS_NAME,
      'name' => 'accepted',
      'label' => 'Accepted',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_STATUS_NAME,
      'name' => 'declined',
      'label' => 'Declined',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);
  }

  /**
   * Creates sale order invoicing status.
   */
  private function createSaleOrderInvoicingStatus() {
    CRM_Core_BAO_OptionGroup::ensureOptionGroupExists([
      'name' => self::SALE_ORDER_INVOICING_STATUS_NANE,
      'title' => ts('Invoicing'),
      'is_reserved' => 1,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_INVOICING_STATUS_NANE,
      'name' => 'no_invoices',
      'label' => 'No Invoices',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_INVOICING_STATUS_NANE,
      'name' => 'partially_invoiced',
      'label' => 'Partially Invoiced',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_INVOICING_STATUS_NANE,
      'name' => 'fully_invoiced',
      'label' => 'Fully Invoiced',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);
  }

  /**
   * Creates sale order payments status.
   */
  private function createSaleOrderPaymentStatus() {
    CRM_Core_BAO_OptionGroup::ensureOptionGroupExists([
      'name' => self::SALE_ORDER_PAYMENT_STATUS_NANE,
      'title' => ts('Payments'),
      'is_reserved' => 1,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_PAYMENT_STATUS_NANE,
      'name' => 'no_payments',
      'label' => 'No Payments',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_PAYMENT_STATUS_NANE,
      'name' => 'no_payments',
      'label' => 'No Payments',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_PAYMENT_STATUS_NANE,
      'name' => 'partially_paid',
      'label' => 'Partially Paid',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_PAYMENT_STATUS_NANE,
      'name' => 'fully_paid',
      'label' => 'Fully Paid',
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);

    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::SALE_ORDER_PAYMENT_STATUS_NANE,
      'name' => 'over_paid',
      'label' => 'Over Paid',
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
      'name' => [
        'IN' => [
          self::SALE_ORDER_STATUS_NAME,
          self::SALE_ORDER_INVOICING_STATUS_NANE,
          self::SALE_ORDER_PAYMENT_STATUS_NANE,
        ],
      ],
      'api.OptionGroup.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * {@inheritDoc}
   */
  protected function toggle($status): void {
    civicrm_api3('OptionGroup', 'get', [
      'sequential' => 1,
      'name' => [
        'IN' => [
          self::SALE_ORDER_STATUS_NAME,
          self::SALE_ORDER_INVOICING_STATUS_NANE,
          self::SALE_ORDER_PAYMENT_STATUS_NANE,
        ],
      ],
      'api.OptionGroup.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
