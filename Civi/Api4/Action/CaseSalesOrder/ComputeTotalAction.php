<?php

namespace Civi\Api4\Action\CaseSalesOrder;

use Civi\Api4\Generic\Result;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Traits\DAOActionTrait;
use CRM_Civicase_BAO_CaseSalesOrder as CaseSalesOrderBAO;

/**
 * Computes the total of a sales order.
 */
class ComputeTotalAction extends AbstractAction {
  use DAOActionTrait;

  /**
   * Sales order line items.
   *
   * @var array
   * @required
   */
  protected $lineItems;

  /**
   * {@inheritDoc}
   */
  public function _run(Result $result) { // phpcs:ignore
    if (is_array($this->lineItems)) {
      $result[] = CaseSalesOrderBAO::computeTotal($this->lineItems);
    }
  }

}
