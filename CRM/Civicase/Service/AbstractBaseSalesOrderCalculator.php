<?php

use Civi\Api4\OptionValue;

/**
 * An Abstract class for SalesOrder and Opportunity statuses and amounts.
 */
abstract class CRM_Civicase_Service_AbstractBaseSalesOrderCalculator {

  const
    INVOICING_STATUS_NO_INVOICES = 'no_invoices',
    INVOICING_STATUS_PARTIALLY_INVOICED = 'partially_invoiced',
    INVOICING_STATUS_FULLY_INVOICED = 'fully_invoiced',
    PAYMENT_STATUS_NO_PAYMENTS = 'no_payments',
    PAYMENT_STATUS_PARTIALLY_PAID = 'partially_paid',
    PAYMENT_STATUS_OVERPAID = 'over_paid',
    PAYMENT_STATUS_FULLY_PAID = 'fully_paid';

  /**
   * Case Sales Order payment status option values.
   *
   * @var array
   */
  protected array $paymentStatusOptionValues;
  /**
   * Case Sales Order Invoicing status option values.
   *
   * @var array
   */
  protected array $invoicingStatusOptionValues;

  /**
   * AbstractBaseSalesOrderCalculator constructor.
   */
  public function __construct() {
    $this->paymentStatusOptionValues = $this->getOptionValues('case_sales_order_payment_status');
    $this->invoicingStatusOptionValues = $this->getOptionValues('case_sales_order_invoicing_status');
  }

  /**
   * Gets option values by option group name.
   *
   * @param string $name
   *   Option group name.
   *
   * @return array
   *   Option values.
   *
   * @throws API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function getOptionValues($name) {
    return OptionValue::get(FALSE)
      ->addSelect('*')
      ->addWhere('option_group_id:name', '=', $name)
      ->execute()
      ->getArrayCopy();
  }

  /**
   * Gets status (option values' value) from the given options.
   *
   * @param string $needle
   *   Search value.
   * @param array $options
   *   Option value.
   *
   * @return string
   *   Option values' value.
   */
  protected function getValueFromOptionValues($needle, $options) {
    $key = array_search($needle, array_column($options, 'name'));

    return $options[$key]['value'];
  }

  /**
   * Gets status (option values' label) from the given options.
   *
   * @param string $needle
   *   Search value.
   * @param array $options
   *   Option value.
   *
   * @return string
   *   Option values' value.
   */
  protected function getLabelFromOptionValues($needle, $options) {
    $key = array_search($needle, array_column($options, 'name'));

    return $options[$key]['label'];
  }

}
