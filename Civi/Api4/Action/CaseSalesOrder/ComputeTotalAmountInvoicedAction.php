<?php

namespace Civi\Api4\Action\CaseSalesOrder;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\Generic\Traits\DAOActionTrait;

/**
 * Computes Total Amount Invoice Action.
 */
class ComputeTotalAmountInvoicedAction extends AbstractAction {
  use DAOActionTrait;


  /**
   * Sales order ID.
   */
  protected string $salesOrderID;

  /**
   * {@inheritDoc}
   */
  public function _run(Result $result) { // phpcs:ignore
    if (!$this->salesOrderID) {
      return;
    }
    $service = new \CRM_Civicase_Service_CaseSalesOrderContributionCalculator($this->salesOrderID);
    $result['amount'] = $service->calculateTotalInvoicedAmount();
  }

  /**
   * Sets Sales Order ID.
   */
  public function setSalesOrderId(string $salesOrderId) {
    $this->salesOrderID = $salesOrderId;
  }

}
