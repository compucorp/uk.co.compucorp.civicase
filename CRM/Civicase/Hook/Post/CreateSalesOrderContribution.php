<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;

/**
 * Handles sales order contribution post processing.
 */
class CRM_Civicase_Hook_Post_CreateSalesOrderContribution {

  /**
   * Updates CaseSaleOrder status when creating a quotation contribution.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   * @param mixed $objectId
   *   Object ID.
   * @param object $objectRef
   *   Object reference.
   */
  public function run($op, $objectName, $objectId, &$objectRef) {
    if (!$this->shouldRun($op, $objectName)) {
      return;
    }

    $salesOrderId = CRM_Utils_Request::retrieve('sales_order', 'Integer');
    if (empty($salesOrderId)) {
      $salesOrderId = $this->getQuotationID($objectId)['Opportunity_Details.Quotation'];
    }

    if (empty($salesOrderId)) {
      return;
    }

    $salesOrder = CaseSalesOrder::get()
      ->addSelect('status_id', 'case_id')
      ->addWhere('id', '=', $salesOrderId)
      ->execute()
      ->first();

    $salesOrderStatusId = CRM_Utils_Request::retrieve('sales_order_status_id', 'Integer');
    if (empty($salesOrderStatusId)) {
      $salesOrder = $salesOrder['status_id'];
    }

    $transaction = CRM_Core_Transaction::create();
    try {
      $caseSaleOrderContributionService = new CRM_Civicase_Service_CaseSalesOrderContributionCalculator($salesOrderId);
      $paymentStatusID = $caseSaleOrderContributionService->calculatePaymentStatus();
      $invoicingStatusID = $caseSaleOrderContributionService->calculateInvoicingStatus();

      CaseSalesOrder::update()
        ->addWhere('id', '=', $salesOrderId)
        ->addValue('status_id', $salesOrderStatusId)
        ->addValue('invoicing_status_id', $invoicingStatusID)
        ->addValue('payment_status_id', $paymentStatusID)
        ->execute();

      $caseId = $salesOrder['case_id'];
      if (empty($caseId)) {
        return;
      }
      $calculator = new CRM_Civicase_Service_CaseSalesOrderOpportunityCalculator($caseId);
      $calculator->updateOpportunityFinancialDetails();
    }
    catch (\Throwable $th) {
      $transaction->rollback();
      CRM_Core_Error::statusBounce(ts('Error creating sales order contribution'));
    }
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
    return strtolower($objectName) == 'contribution' && $op == 'create';
  }

  /**
   * Gets quotation ID by contribution ID.
   */
  private function getQuotationId($id) {
    return Contribution::get()
      ->addSelect('Opportunity_Details.Quotation')
      ->addWhere('id', '=', $id)
      ->execute()
      ->first();
  }

}
