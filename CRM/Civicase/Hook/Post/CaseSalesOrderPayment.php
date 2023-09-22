<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;

/**
 * Handles CaseSalesOrder payment processing.
 */
class CRM_Civicase_Hook_Post_CaseSalesOrderPayment {

  /**
   * Updates CaseSalesOrder statuses when creating a payment transaction.
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
    if (!$this->shouldRun($op, $objectName, $objectRef)) {
      return;
    }

    $financialTrnxId = $objectRef->financial_trxn_id;
    if (empty($financialTrnxId)) {
      return;
    }

    $contributionId = $this->getContributionId($financialTrnxId);
    if (empty($contributionId)) {
      return;
    }

    $contribution = Contribution::get()
      ->addSelect('Opportunity_Details.Case_Opportunity', 'Opportunity_Details.Quotation')
      ->addWhere('id', '=', $contributionId)
      ->execute()
      ->first();

    $this->updateQuotationFinancialStatuses($contribution['Opportunity_Details.Quotation']);
    $this->updateCaseOpportunityFinancialDetails($contribution['Opportunity_Details.Case_Opportunity']);
  }

  /**
   * Updates CaseSalesOrder financial statuses.
   *
   * @param int $salesOrderID
   *   CaseSalesOrder ID.
   */
  private function updateQuotationFinancialStatuses(int $salesOrderID): void {
    if (empty($salesOrderID)) {
      return;
    }

    $transaction = CRM_Core_Transaction::create();

    try {
      $caseSaleOrderContributionService = new CRM_Civicase_Service_CaseSalesOrderContributionCalculator($salesOrderID);
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
   * Updates Case financial statuses.
   *
   * @param int? $caseId
   *   CaseSalesOrder ID.
   */
  private function updateCaseOpportunityFinancialDetails(?int $caseId) {
    if (empty($caseId)) {
      return;
    }

    try {
      $calculator = new CRM_Civicase_Service_CaseSalesOrderOpportunityCalculator($caseId);
      $calculator->updateOpportunityFinancialDetails();
    }
    catch (\Throwable $th) {
      CRM_Core_Error::statusBounce(ts('Error updating opportunity details'));
    }
  }

  /**
   * Gets Contribution ID by Financial Transaction ID.
   */
  private function getContributionId($financialTrxnId) {
    $entityFinancialTrxn = civicrm_api3('EntityFinancialTrxn', 'get', [
      'sequential' => 1,
      'entity_table' => 'civicrm_contribution',
      'financial_trxn_id' => $financialTrxnId,
    ]);

    if (empty($entityFinancialTrxn['values'][0])) {
      return NULL;
    }

    return $entityFinancialTrxn['values'][0]['entity_id'];
  }

  /**
   * Determines if the hook should run or not.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   * @param string $objectRef
   *   The hook object reference.
   *
   * @return bool
   *   returns a boolean to determine if hook will run or not.
   */
  private function shouldRun($op, $objectName, $objectRef) {
    return $objectName == 'EntityFinancialTrxn' && $op == 'create' && property_exists($objectRef, 'financial_trxn_id');
  }

}
