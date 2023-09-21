<?php

namespace Civi\Api4\Action\CaseSalesOrder;

use Civi\Api4\CaseSalesOrderLine;
use Civi\Api4\Generic\AbstractSaveAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\Generic\Traits\DAOActionTrait;
use CRM_Civicase_BAO_CaseSalesOrder as CaseSalesOrderBAO;
use CRM_Core_Transaction;

/**
 * {@inheritDoc}
 */
class SalesOrderSaveAction extends AbstractSaveAction {
  use DAOActionTrait;

  /**
   * {@inheritDoc}
   */
  public function _run(Result $result) { // phpcs:ignore
    foreach ($this->records as &$record) {
      $record += $this->defaults;
      $this->formatWriteValues($record);
      $this->matchExisting($record);
      if (empty($record['id'])) {
        $this->fillDefaults($record);
      }
    }
    $this->validateValues();

    $resultArray = $this->writeRecord($this->records);

    $result->exchangeArray($resultArray);
  }

  /**
   * {@inheritDoc}
   */
  protected function writeRecord($items) {
    $transaction = CRM_Core_Transaction::create();

    try {
      $output = [];
      foreach ($items as $salesOrder) {
        $lineItems = $salesOrder['items'];
        $total = CaseSalesOrderBAO::computeTotal($lineItems);
        $salesOrder['total_before_tax'] = $total['totalBeforeTax'];
        $salesOrder['total_after_tax'] = $total['totalAfterTax'];

        $saleOrderId = $salesOrder['id'] ?? NULL;
        $caseSaleOrderContributionService = new \CRM_Civicase_Service_CaseSaleOrderContribution($saleOrderId);
        $salesOrder['payment_status_id'] = $caseSaleOrderContributionService->calculateInvoicingStatus();
        $salesOrder['invoicing_status_id'] = $caseSaleOrderContributionService->calculatePaymentStatus();

        $salesOrders = $this->writeObjects([$salesOrder]);
        $result = array_pop($salesOrders);

        $caseSalesOrderLineAPI = CaseSalesOrderLine::save();
        $this->removeStaleLineItems($salesOrder);
        if (!empty($result) && !empty($lineItems)) {
          array_walk($lineItems, function (&$lineItem) use ($result, $caseSalesOrderLineAPI) {
            $lineItem['sales_order_id'] = $result['id'];
            $lineItem['subtotal_amount'] = $lineItem['unit_price'] * $lineItem['quantity'] * ((100 - $lineItem['discounted_percentage']) / 100);
            $caseSalesOrderLineAPI->addRecord($lineItem);
          });

          $result['items'] = $caseSalesOrderLineAPI->execute()->jsonSerialize();
        }

        $output[] = $result;
      }

      return $output;
    }
    catch (\Exception $e) {
      $transaction->rollback();

      throw $e;
    }
  }

  /**
   * Delete line items that have been detached.
   *
   * @param array $salesOrder
   *   Array of the salesorder to remove stale line items for.
   */
  public function removeStaleLineItems(array $salesOrder) {
    if (empty($salesOrder['id'])) {
      return;
    }

    $lineItemsInUse = array_column($salesOrder['items'], 'id');

    if (empty($lineItemsInUse)) {
      return;
    }

    CaseSalesOrderLine::delete()
      ->addWhere('sales_order_id', '=', $salesOrder['id'])
      ->addWhere('id', 'NOT IN', $lineItemsInUse)
      ->execute();
  }

}
