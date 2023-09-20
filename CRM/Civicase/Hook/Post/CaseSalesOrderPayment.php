<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;

/**
 * Handles CaseSalesOrder payment processing.
 */
class CRM_Civicase_Hook_Post_CaseSalesOrderPayment {

  /**
   * Updates CaseSaleOrder statuses when creating a payment transcation.
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

    $entityFinancialTrxn = civicrm_api3('EntityFinancialTrxn', 'get', [
      'sequential' => 1,
      'entity_table' => 'civicrm_contribution',
      'financial_trxn_id' => $objectRef->financial_trxn_id,
    ]);

    if (empty($entityFinancialTrxn['values'][0])) {
      return;
    }

    $contributionId = $entityFinancialTrxn['values'][0]['entity_id'];

    $salesOrderID = Contribution::get()
      ->addSelect('Opportunity_Details.Quotation')
      ->addWhere('id', '=', $contributionId)
      ->execute()
      ->first()['Opportunity_Details.Quotation'];

    if (empty($salesOrderID)) {
      return;
    }

    $transaction = CRM_Core_Transaction::create();

    try {
      $caseSaleOrderContributionService = new CRM_Civicase_Service_CaseSaleOrderContribution($salesOrderID);
      $paymentStatusID = $caseSaleOrderContributionService->calculatePaymentStatus();
      $invoicingStatusID = $caseSaleOrderContributionService->calculateInvoicingStatus();

      CaseSalesOrder::update()
        ->addWhere('id', '=', $salesOrderID)
        ->addValue('invoicing_status_id', $invoicingStatusID)
        ->addValue('payment_status_id', $paymentStatusID)
        ->execute();

      $transaction->commit();
    }
    catch (\Throwable $th) {
      $transaction->rollback();
      CRM_Core_Error::statusBounce(ts('Error updating sales order statues'));
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
    return $objectName == 'EntityFinancialTrxn' && $op == 'create';
  }

}
