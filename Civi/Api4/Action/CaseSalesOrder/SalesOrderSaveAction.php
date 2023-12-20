<?php

namespace Civi\Api4\Action\CaseSalesOrder;

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\CaseSalesOrderLine;
use Civi\Api4\Generic\AbstractSaveAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\Generic\Traits\DAOActionTrait;
use Civi\Api4\Product;
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
        $this->fillMandatoryFields($record);
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
        $this->validateLinItemProductPrice($lineItems);
        $total = CaseSalesOrderBAO::computeTotal($lineItems);
        $salesOrder['total_before_tax'] = $total['totalBeforeTax'];
        $salesOrder['total_after_tax'] = $total['totalAfterTax'];

        $saleOrderId = $salesOrder['id'] ?? NULL;
        $caseSaleOrderContributionService = new \CRM_Civicase_Service_CaseSalesOrderContributionCalculator($saleOrderId);
        $salesOrder['payment_status_id'] = $caseSaleOrderContributionService->calculateInvoicingStatus();
        $salesOrder['invoicing_status_id'] = $caseSaleOrderContributionService->calculatePaymentStatus();

        if (!is_null($saleOrderId)) {
          $this->updateOpportunityDetails($saleOrderId);
        }

        $salesOrders = $this->writeObjects([$salesOrder]);
        $result = array_pop($salesOrders);

        $caseSalesOrderLineAPI = CaseSalesOrderLine::save(FALSE);
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

    CaseSalesOrderLine::delete(FALSE)
      ->addWhere('sales_order_id', '=', $salesOrder['id'])
      ->addWhere('id', 'NOT IN', $lineItemsInUse)
      ->execute();
  }

  /**
   * Updates sales order's case opportunity details.
   *
   * @param int $salesOrderId
   *   Sales Order Id.
   */
  private function updateOpportunityDetails($salesOrderId): void {
    $caseSalesOrder = CaseSalesOrder::get(FALSE)
      ->addSelect('case_id')
      ->addWhere('id', '=', $salesOrderId)
      ->execute()
      ->first();

    if (empty($caseSalesOrder) || empty($caseSalesOrder['case_id'])) {
      return;
    }

    $caseSaleOrderContributionService = new \CRM_Civicase_Service_CaseSalesOrderOpportunityCalculator($caseSalesOrder['case_id']);
    $caseSaleOrderContributionService->updateOpportunityFinancialDetails();
  }

  /**
   * Fill mandatory fields.
   *
   * @param array $params
   *   Single Sales Order Record.
   */
  protected function fillMandatoryFields(&$params) {
    $saleOrderId = $params['id'] ?? NULL;
    $caseSaleOrderContributionService = new \CRM_Civicase_Service_CaseSalesOrderContributionCalculator($saleOrderId);
    $params['payment_status_id'] = $caseSaleOrderContributionService->calculateInvoicingStatus();
    $params['invoicing_status_id'] = $caseSaleOrderContributionService->calculatePaymentStatus();
  }

  /**
   * Ensures the product price doesnt exceed the max price.
   *
   * @param array $lineItems
   *   Sales Order line items.
   */
  protected function validateLinItemProductPrice(array &$lineItems) {
    array_walk($lineItems, function (&$lineItem) {
      if (!empty($lineItem['product_id'])) {
        $product = Product::get(FALSE)
          ->addSelect('cost')
          ->addWhere('id', '=', $lineItem['product_id'])
          ->execute()
          ->first();

        if ($product && !empty($product['cost']) && $product['cost'] < $lineItem['subtotal_amount']) {
          $lineItem['unit_price'] = $product['cost'];
          $lineItem['quantity'] = 1;
          $lineItem['subtotal_amount'] = CaseSalesOrderBAO::getSubTotal($lineItem);
        }
      }
    });
  }

}
