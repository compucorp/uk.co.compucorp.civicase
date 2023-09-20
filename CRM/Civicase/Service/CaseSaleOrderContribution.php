<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;
use Civi\Api4\OptionValue;

/**
 * Case Sale Order Contribution Service.
 *
 * This class provides calculations for payment and invoices that
 * attached to the sale order.
 */
class CRM_Civicase_Service_CaseSaleOrderContribution {

  /**
   * Case Sales Order object.
   *
   * @var array|null
   */
  private ?array $salesOrder;
  /**
   * Case Sales Order payment status option values.
   *
   * @var array
   */
  private array $paymentStatusOptionValues;
  /**
   * Case Sales Order Invoicing status option values.
   *
   * @var array
   */
  private array $invoicingStatusOptionValues;
  /**
   * List of contributions that links to the sales order.
   *
   * @var array
   */
  private array $contributions;
  /**
   * Total invoiced amount.
   *
   * @var float
   */
  private float $totalInvoicedAmount;
  /**
   * Total payments amount.
   *
   * @var float
   */
  private float $totalPaymentsAmount;

  /**
   * Class constructor.
   *
   * @param string $salesOrderId
   *   Sales Order ID.
   *
   * @throws API_Exception
   * @throws CiviCRM_API3_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function __construct($salesOrderId) {
    $this->salesOrder = $this->getSalesOrder($salesOrderId);
    $this->paymentStatusOptionValues = $this->getOptionValues('case_sales_order_payment_status');
    $this->invoicingStatusOptionValues = $this->getOptionValues('case_sales_order_invoicing_status');
    $this->contributions = $this->getContributions();
    $this->totalInvoicedAmount = $this->getTotalInvoicedAmount();
    $this->totalPaymentsAmount = $this->getTotalPaymentsAmount();
  }

  /**
   * Calculates total invoiced amount.
   *
   * @return float
   *   Total invoiced amount.
   */
  public function calculateTotalInvoicedAmount(): float {

    return $this->getTotalInvoicedAmount();
  }

  /**
   * Calculates total paid amount.
   *
   * @return float
   *   Total paid amounts.
   */
  public function calculateTotalPaidAmount(): float {
    return $this->getTotalPaymentsAmount();
  }

  /**
   * Calculates invoicing status.
   *
   * @return string
   *   Invoicing status option value's value
   */
  public function calculateInvoicingStatus() {
    if (empty($this->salesOrder) || empty($this->contributions)) {
      return $this->getStatus('no_invoices', $this->invoicingStatusOptionValues);
    }

    $quotationTotalAmount = $this->salesOrder['total_after_tax'];
    if ($this->totalInvoicedAmount < $quotationTotalAmount) {
      return $this->getStatus('partially_invoiced', $this->invoicingStatusOptionValues);
    }

    return $this->getStatus('fully_invoiced', $this->invoicingStatusOptionValues);
  }

  /**
   * Calculates payment status.
   *
   * @return string
   *   Payment status option value's value
   */
  public function calculatePaymentStatus() {
    if (empty($this->salesOrder) || empty($this->contributions) || !($this->totalPaymentsAmount > 0)) {

      return $this->getStatus('no_payments', $this->paymentStatusOptionValues);
    }

    if ($this->totalPaymentsAmount < $this->totalInvoicedAmount) {
      return $this->getStatus('partially_paid', $this->paymentStatusOptionValues);
    }

    return $this->getStatus('fully_paid', $this->paymentStatusOptionValues);
  }

  /**
   * Gets list of contributions from the sale order.
   *
   * @return array
   *   List of contributions.
   *
   * @throws API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function getContributions() {
    return Contribution::get(FALSE)
      ->addWhere('Opportunity_Details.Quotation', '=', $this->salesOrder['id'])
      ->execute()
      ->getArrayCopy();
  }

  /**
   * Gets Sales Order by SaleOrder ID.
   *
   * @param string $saleOrderId
   *   Sales Order ID.
   *
   * @return array|null
   *   Sales Order array or null
   *
   * @throws API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function getSalesOrder($saleOrderId) {
    return CaseSalesOrder::get()
      ->addWhere('id', '=', $saleOrderId)
      ->execute()
      ->first();
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
  private function getOptionValues($name) {
    return OptionValue::get()
      ->addSelect('*')
      ->addWhere('option_group_id:name', '=', $name)
      ->execute()
      ->getArrayCopy();
  }

  /**
   * Gets total invoiced amount.
   *
   * @return float
   *   Total invoiced amount.
   */
  private function getTotalInvoicedAmount(): float {
    $totalInvoicedAmount = 0.0;
    foreach ($this->contributions as $contribution) {
      $totalInvoicedAmount += $contribution['total_amount'];
    }

    return $totalInvoicedAmount;
  }

  /**
   * Gets total payments amount.
   *
   * @return float
   *   Total payment amount.
   */
  private function getTotalPaymentsAmount(): float {
    $totalPaymentsAmount = 0.0;
    foreach ($this->contributions as $contribution) {
      $payments = civicrm_api3('Payment', 'get', [
        'sequential' => 1,
        'contribution_id' => $contribution['id'],
      ])['values'];

      foreach ($payments as $payment) {
        $totalPaymentsAmount += $payment['total_amount'];
      }
    }

    return $totalPaymentsAmount;
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
  private function getStatus($needle, $options) {
    $key = array_search($needle, array_column($options, 'name'));

    return $options[$key]['value'];
  }

}
