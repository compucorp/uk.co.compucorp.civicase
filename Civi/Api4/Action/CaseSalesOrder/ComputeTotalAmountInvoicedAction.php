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
   * ID of the sales order (quotation) to compute the invoiced amount for.
   *
   * NOTE: APIv4 derives the public parameter name from this property name,
   * so it must stay camelCase (`salesOrderId`) to match the callers.
   *
   * @var int
   */
  protected $salesOrderId = NULL;

  /**
   * {@inheritDoc}
   */
  public function _run(Result $result) { // phpcs:ignore
    if (empty($this->salesOrderId)) {
      return;
    }
    $service = new \CRM_Civicase_Service_CaseSalesOrderContributionCalculator($this->salesOrderId);
    $result['amount'] = $service->calculateTotalInvoicedAmount();
  }

}
