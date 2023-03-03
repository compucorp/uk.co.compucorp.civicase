<?php

namespace Civi\Api4\Action\CaseSalesOrder;

use CRM_Core_Transaction;
use Civi\Api4\Generic\Result;
use Civi\Api4\CaseSalesOrderLine;
use Civi\Api4\Generic\AbstractSaveAction;
use Civi\Api4\Generic\Traits\DAOActionTrait;
use CRM_Civicase_BAO_CaseSalesOrder as CaseSalesOrderBAO;

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
        $salesOrders = $this->writeObjects([$salesOrder]);
        $result = array_pop($salesOrders);

        $caseSalesOrderLineAPI = CaseSalesOrderLine::save();
        if (!empty($result) && !empty($lineItems)) {
          array_walk($lineItems, function (&$lineItem) use ($result, $caseSalesOrderLineAPI) {
            $lineItem['sales_order_id'] = $result['id'];
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

}
