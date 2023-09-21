<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;

/**
 * Adds Quotation invoice as an attachement.
 */
class CRM_Civicase_Hook_alterMailParams_AttachQuotation {

  /**
   * Attaches quotation to single invoice.
   *
   * @param array $params
   *   Mail parameters.
   * @param string $context
   *   Mail context.
   */
  public function run(array &$params, $context) {
    $shouldAttachQuote = CRM_Utils_Request::retrieve('attach_quote', 'String');

    if (!$this->shouldRun($params, $context, $shouldAttachQuote)) {
      return;
    }

    $rendered = $this->getContributionQuotationInvoice($params['tokenContext']['contributionId']);

    $params['attachments'][] = CRM_Utils_Mail::appendPDF('quotation_invoice.pdf', $rendered['html'], $rendered['format']);
  }

  /**
   * Renders the Invoice for the quotation linked to the contribution.
   *
   * @param int $contributionId
   *   The contribution ID.
   */
  private function getContributionQuotationInvoice($contributionId) {
    $salesOrder = Contribution::get()
      ->addSelect('Opportunity_Details.Quotation')
      ->addWhere('Opportunity_Details.Quotation', 'IS NOT EMPTY')
      ->addWhere('id', '=', $contributionId)
      ->addChain('salesOrder', CaseSalesOrder::get()
        ->addWhere('id', '=', '$Opportunity_Details.Quotation')
      )
      ->execute()
      ->first()['salesOrder'];

    if (empty($salesOrder)) {
      return;
    }

    /** @var \CRM_Civicase_Service_CaseSalesOrderInvoice */
    $invoiceService = new \CRM_Civicase_Service_CaseSalesOrderInvoice(new CRM_Civicase_WorkflowMessage_SalesOrderInvoice());
    return $invoiceService->render($salesOrder[0]['id']);
  }

  /**
   * Determines if the hook will run.
   *
   * @param array $params
   *   Mail parameters.
   * @param string $context
   *   Mail context.
   * @param string $shouldAttachQuote
   *   If the Attach Quote is set.
   *
   * @return bool
   *   returns TRUE if hook should run, FALSE otherwise.
   */
  private function shouldRun(array $params, $context, $shouldAttachQuote) {
    $component = $params['tplParams']['component'] ?? '';
    if ($component !== 'contribute' || $context !== 'messageTemplate' || empty($shouldAttachQuote)) {
      return FALSE;
    }

    return TRUE;
  }

}
