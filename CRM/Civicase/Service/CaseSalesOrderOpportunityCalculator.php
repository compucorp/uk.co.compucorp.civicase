<?php

use Civi\Api4\CiviCase;
use Civi\Api4\Contribution;

/**
 * A service class for calculate opportunity financial amounts and statuses.
 */
class CRM_Civicase_Service_CaseSalesOrderOpportunityCalculator extends CRM_Civicase_Service_AbstractBaseSalesOrderCalculator {
  /**
   * Case's total quoted amount.
   *
   * @var int|float
   */
  private int|float $totalQuotedAmount;
  /**
   * Case's total paid amount.
   *
   * @var int|float
   */
  private int|float $totalPaidAmount;
  /**
   * Case's total invoiced amount.
   *
   * @var int|float
   */
  private int|float $totalInvoicedAmount;
  /**
   * Case ID.
   *
   * @var string
   */
  private string $caseId;
  /**
   * ID of contribution that is being deleted.
   *
   * @var null|int
   */
  private ?int $deletingContributionId;

  /**
   * Constructor for CaseSalesOrderOpportunityCalculator class.
   *
   * @param string $caseId
   *   Case Id.
   * @param null|int $deletingContributionId
   *   ID of contribution that is being deleted.
   */
  public function __construct($caseId, ?int $deletingContributionId = NULL) {
    parent::__construct();
    $this->caseId = $caseId;
    $this->deletingContributionId = $deletingContributionId;
    $contributions = $this->getContributions($caseId);
    $this->calculateOpportunityFinancialAmount($contributions);
  }

  /**
   * Calculates total invoiced amount.
   *
   * @return float
   *   Total invoiced amount.
   */
  public function calculateTotalInvoicedAmount(): float {
    return $this->totalInvoicedAmount;
  }

  /**
   * Calculates total paid amount.
   *
   * @return float
   *   Total paid amounts.
   */
  public function calculateTotalPaidAmount(): float {
    return $this->totalPaidAmount;
  }

  /**
   * Calculates total quoted amount.
   *
   * @return float
   *   Total paid amounts.
   */
  public function calculateTotalQuotedAmount(): float {
    return $this->totalQuotedAmount;
  }

  /**
   * Calculates opportunity invoicing status.
   *
   * @return string
   *   Invoicing status option value's value
   */
  public function calculateInvoicingStatus() {
    if (!($this->totalInvoicedAmount > 0)) {
      return $this->getLabelFromOptionValues(parent::INVOICING_STATUS_NO_INVOICES, $this->invoicingStatusOptionValues);
    }

    if ($this->totalInvoicedAmount < $this->totalQuotedAmount) {
      return $this->getLabelFromOptionValues(parent::INVOICING_STATUS_PARTIALLY_INVOICED, $this->invoicingStatusOptionValues);
    }

    return $this->getLabelFromOptionValues(parent::INVOICING_STATUS_FULLY_INVOICED, $this->invoicingStatusOptionValues);
  }

  /**
   * Calculates opportunity payment status.
   *
   * @return string
   *   Payment status option value's value
   */
  public function calculatePaymentStatus() {
    if (!($this->totalPaidAmount > 0)) {
      return $this->getLabelFromOptionValues(parent::PAYMENT_STATUS_NO_PAYMENTS, $this->paymentStatusOptionValues);
    }

    if ($this->totalPaidAmount < $this->totalQuotedAmount) {
      return $this->getLabelFromOptionValues(parent::PAYMENT_STATUS_PARTIALLY_PAID, $this->paymentStatusOptionValues);
    }

    if ($this->totalPaidAmount > $this->totalQuotedAmount) {
      return $this->getLabelFromOptionValues(parent::PAYMENT_STATUS_OVERPAID, $this->paymentStatusOptionValues);

    }

    return $this->getLabelFromOptionValues(parent::PAYMENT_STATUS_FULLY_PAID, $this->paymentStatusOptionValues);
  }

  /**
   * Updates opportunity financial details.
   */
  public function updateOpportunityFinancialDetails(): void {
    CiviCase::update()
      ->addValue('Case_Opportunity_Details.Total_Amount_Quoted', $this->calculateTotalQuotedAmount())
      ->addValue('Case_Opportunity_Details.Total_Amount_Invoiced', $this->calculateTotalInvoicedAmount())
      ->addValue('Case_Opportunity_Details.Invoicing_Status', $this->calculateInvoicingStatus())
      ->addValue('Case_Opportunity_Details.Total_Amounts_Paid', $this->calculateTotalPaidAmount())
      ->addValue('Case_Opportunity_Details.Payments_Status', $this->calculatePaymentStatus())
      ->addWhere('id', '=', $this->caseId)
      ->execute();
  }

  /**
   * Calculates opportunity financial amounts.
   *
   * @param array $contributions
   *   List of contributions that link to the opportunity.
   */
  private function calculateOpportunityFinancialAmount($contributions) {
    $totalQuotedAmount = 0;
    $totalInvoicedAmount = 0;
    $totalPaidAmount = 0;
    foreach ($contributions as $contribution) {
      $salesOrderId = $contribution['Opportunity_Details.Quotation'];
      $caseSaleOrderContributionService = new CRM_Civicase_Service_CaseSalesOrderContributionCalculator($salesOrderId);

      $totalQuotedAmount += $caseSaleOrderContributionService->getQuotedAmount();
      $totalPaidAmount += $caseSaleOrderContributionService->calculateTotalPaidAmount();
      $totalInvoicedAmount += $caseSaleOrderContributionService->calculateTotalInvoicedAmount();
    }

    $this->totalQuotedAmount = $totalQuotedAmount;
    $this->totalPaidAmount = $totalPaidAmount;
    $this->totalInvoicedAmount = $totalInvoicedAmount;
  }

  /**
   * Gets Contributions by case Id.
   *
   * @param int $caseId
   *   List of contributions that link to the opportunity.
   */
  private function getContributions($caseId) {
    $contributions = Contribution::get(FALSE)
      ->addSelect('*', 'Opportunity_Details.Quotation')
      ->addWhere('Opportunity_Details.Case_Opportunity', '=', $caseId);

    if ($this->deletingContributionId !== NULL) {
      $contributions->addWhere('id', '!=', $this->deletingContributionId);
    }

    return $contributions->execute()->getArrayCopy();
  }

}
