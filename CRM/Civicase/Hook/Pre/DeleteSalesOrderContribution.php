<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;

/**
 * Handles Precessing Contribution deletion.
 */
class CRM_Civicase_Hook_Pre_DeleteSalesOrderContribution {

  /**
   * Updates CaseSaleOrder and Opportunity statuses and financial details.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   * @param mixed $objectId
   *   Object ID.
   * @param array &$params
   *   Array of an entity.
   */
  public function run($op, $objectName, $objectId, &$params) {
    if (!$this->shouldRun($op, $objectName)) {
      return;
    }

    $salesOrderId = $this->getQuotationId($objectId);
    if (empty($salesOrderId)) {
      return;
    }

    $caseSaleOrderContributionService = new CRM_Civicase_Service_CaseSalesOrderContributionCalculator($salesOrderId);
    $invoicingStatusId = $caseSaleOrderContributionService->calculateInvoicingStatus();
    $paymentStatusId = $caseSaleOrderContributionService->calculatePaymentStatus();

    CaseSalesOrder::update()
      ->addWhere('id', '=', $salesOrderId)
      ->addValue('invoicing_status_id', $invoicingStatusId)
      ->addValue('payment_status_id', $paymentStatusId)
      ->execute();

    $caseSalesOrder = CaseSalesOrder::get()
      ->addSelect('case_id')
      ->addWhere('id', '=', $salesOrderId)
      ->execute()
      ->first();

    $caseSaleOrderContributionService = new \CRM_Civicase_Service_CaseSalesOrderOpportunityCalculator($caseSalesOrder['case_id']);
    $caseSaleOrderContributionService->updateOpportunityFinancialDetails();
  }

  /**
   * Determines if the hook should run or not.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   *
   * @return bool
   *   returns a boolean to determine if hook will run or not.
   */
  private function shouldRun($op, $objectName) {
    return strtolower($objectName) == 'contribution' && $op == 'delete';
  }

  /**
   * Gets quotation ID by contribution ID.
   */
  private function getQuotationId($id) {
    return Contribution::get()
      ->addSelect('Opportunity_Details.Quotation')
      ->addWhere('id', '=', $id)
      ->execute()
      ->first()['Opportunity_Details.Quotation'];
  }

}
