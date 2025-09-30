<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;

/**
 * Adds Quotation invoice as an attachement.
 */
class CRM_Civicase_Hook_alterMailParams_AttachQuotation {

  /**
   * LRU cache for contribution data.
   *
   * @var array
   */
  private static $contributionCache = [];

  /**
   * LRU order tracking for contribution cache.
   *
   * @var array
   */
  private static $contributionCacheOrder = [];

  /**
   * LRU cache for sales order data.
   *
   * @var array
   */
  private static $salesOrderCache = [];

  /**
   * LRU order tracking for sales order cache.
   *
   * @var array
   */
  private static $salesOrderCacheOrder = [];

  /**
   * Counter for processed quotations.
   *
   * @var int
   */
  private static $processedQuotations = 0;

  /**
   * Maximum cache size for LRU eviction.
   *
   * @var int
   */
  private static $maxCacheSize = 100;

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

    $contributionId = $params['tokenContext']['contributionId'] ?? $params['tplParams']['id'];

    try {
      $rendered = $this->getContributionQuotationInvoice($contributionId);
      if (empty($rendered)) {
        return;
      }

      $attachment = CRM_Utils_Mail::appendPDF('quotation_invoice.pdf', $rendered['html'], $rendered['format']);

      if ($attachment) {
        $params['attachments']['quotaition_invoice'] = $attachment;
      }

      // Increment counter and manage memory adaptively.
      self::$processedQuotations++;
      $this->performMemoryManagement();
    }
    catch (Exception $e) {
      \Civi::log()->error('AttachQuotation processing failed: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Renders the Invoice for the quotation linked to the contribution.
   *
   * @param int $contributionId
   *   The contribution ID.
   */
  private function getContributionQuotationInvoice($contributionId) {
    // Check cache first (LRU access).
    if (isset(self::$contributionCache[$contributionId])) {
      // Move to end of order array (most recently used).
      $this->updateLruOrder(self::$contributionCacheOrder, $contributionId);

      $quotationId = self::$contributionCache[$contributionId];
      if (isset(self::$salesOrderCache[$quotationId])) {
        // Move to end of order array (most recently used).
        $this->updateLruOrder(self::$salesOrderCacheOrder, $quotationId);
        return self::$salesOrderCache[$quotationId];
      }
    }

    try {
      $salesOrder = Contribution::get(FALSE)
        ->addSelect('Opportunity_Details.Quotation')
        ->addWhere('Opportunity_Details.Quotation', 'IS NOT EMPTY')
        ->addWhere('id', '=', $contributionId)
        ->addChain('salesOrder', CaseSalesOrder::get(FALSE)
          ->addWhere('id', '=', '$Opportunity_Details.Quotation')
        )
        ->execute()
        ->first()['salesOrder'];
    }
    catch (\Throwable $th) {
      return;
    }

    if (empty($salesOrder)) {
      return;
    }

    $quotationId = $salesOrder[0]['id'];

    // Cache the contribution->quotation mapping with LRU.
    $this->addToLruCache(self::$contributionCache, self::$contributionCacheOrder, $contributionId, $quotationId);

    /** @var \CRM_Civicase_Service_CaseSalesOrderInvoice */
    $invoiceService = new \CRM_Civicase_Service_CaseSalesOrderInvoice(new CRM_Civicase_WorkflowMessage_SalesOrderInvoice());
    $rendered = $invoiceService->render($quotationId);

    // Cache the rendered result with LRU.
    if ($rendered) {
      $this->addToLruCache(self::$salesOrderCache, self::$salesOrderCacheOrder, $quotationId, $rendered);
    }

    return $rendered;
  }

  /**
   * Manages memory using adaptive garbage collection.
   *
   * Uses event-based GC after PDF generation (heavy operation).
   */
  private function performMemoryManagement() {
    // Event-based GC: After PDF generation (creates complex object graphs).
    CRM_Civicase_Common_GCManager::maybeCollectGarbage('pdf_generation');
  }

  /**
   * Updates LRU order by moving item to end (most recently used).
   */
  private function updateLruOrder(&$orderArray, $key) {
    $index = array_search($key, $orderArray);
    if ($index !== FALSE) {
      // Remove from current position.
      unset($orderArray[$index]);
    }
    // Add to end (most recently used).
    $orderArray[] = $key;
  }

  /**
   * Adds item to LRU cache, evicting least recently used if at capacity.
   */
  private function addToLruCache(&$cache, &$orderArray, $key, $value) {
    // If already exists, update value and move to end.
    if (isset($cache[$key])) {
      $cache[$key] = $value;
      $this->updateLruOrder($orderArray, $key);
      return;
    }

    // If at capacity, remove least recently used item.
    if (count($cache) >= self::$maxCacheSize) {
      $lruKey = array_shift($orderArray);
      unset($cache[$lruKey]);
    }

    // Add new item.
    $cache[$key] = $value;
    $orderArray[] = $key;
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
    if ($component !== 'contribute' || empty($shouldAttachQuote)) {
      return FALSE;
    }

    return TRUE;
  }

}