<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;

/**
 * Case Sale Order Contribution Service.
 *
 * This class provides calculations for payment and invoices that
 * attached to the sale order.
 */
class CRM_Civicase_Service_CaseSalesOrderContributionCalculator extends CRM_Civicase_Service_AbstractBaseSalesOrderCalculator {

  /**
   * Case Sales Order object.
   *
   * @var array|null
   */
  private ?array $salesOrder;

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
    parent::__construct();
    $this->salesOrder = $this->getSalesOrder($salesOrderId);
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
   * Gets SalesOrder Total amount after tax.
   */
  public function getQuotedAmount(): float {
    return $this->salesOrder['total_after_tax'];
  }

  /**
   * Calculates invoicing status.
   *
   * @return string
   *   Invoicing status option value's value
   */
  public function calculateInvoicingStatus() {
    if (empty($this->salesOrder) || empty($this->contributions)) {
      return $this->getValueFromOptionValues(parent::INVOICING_STATUS_NO_INVOICES, $this->invoicingStatusOptionValues);
    }

    $quotationTotalAmount = $this->salesOrder['total_after_tax'];
    if ($this->totalInvoicedAmount < $quotationTotalAmount) {
      return $this->getValueFromOptionValues(parent::INVOICING_STATUS_PARTIALLY_INVOICED, $this->invoicingStatusOptionValues);
    }

    return $this->getValueFromOptionValues(parent::INVOICING_STATUS_FULLY_INVOICED, $this->invoicingStatusOptionValues);
  }

  /**
   * Calculates payment status.
   *
   * @return string
   *   Payment status option value's value
   */
  public function calculatePaymentStatus() {
    if (empty($this->salesOrder) || empty($this->contributions) || !($this->totalPaymentsAmount > 0)) {

      return $this->getValueFromOptionValues(parent::PAYMENT_STATUS_NO_PAYMENTS, $this->paymentStatusOptionValues);
    }

    if ($this->totalPaymentsAmount < $this->totalInvoicedAmount) {
      return $this->getValueFromOptionValues(parent::PAYMENT_STATUS_PARTIALLY_PAID, $this->paymentStatusOptionValues);
    }

    if ($this->totalPaymentsAmount > $this->totalInvoicedAmount) {
      return $this->getValueFromOptionValues(parent::PAYMENT_STATUS_OVERPAID, $this->paymentStatusOptionValues);
    }

    return $this->getValueFromOptionValues(parent::PAYMENT_STATUS_FULLY_PAID, $this->paymentStatusOptionValues);
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
    if (empty($this->salesOrder) || empty($this->salesOrder['id'])) {
      return [];
    }

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

}
