<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\CaseSalesOrderContribution;

/**
 * Handles sales order contribution post processing.
 */
class CRM_Civicase_Hook_Post_CreateSalesOrderContribution {

  /**
   * Creates Sales Order Contribtution.
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
    $salesOrderId = CRM_Utils_Request::retrieve('sales_order', 'Integer');
    $salesOrderStatusId = CRM_Utils_Request::retrieve('sales_order_status_id', 'Integer');

    if (!$this->shouldRun($op, $objectName, $salesOrderId)) {
      return;
    }

    CaseSalesOrderContribution::create()
      ->addValue('case_sales_order_id', $salesOrderId)
      ->addValue('contribution_id', $objectId)
      ->execute();

    CaseSalesOrder::update()
      ->addWhere('id', '=', $salesOrderId)
      ->addValue('status_id', $salesOrderStatusId)
      ->execute();
  }

  /**
   * Determines if the hook should run or not.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   * @param string $salesOrderId
   *   The sales order that triggered the contribution (if any).
   *
   * @return bool
   *   returns a boolean to determine if hook will run or not.
   */
  private function shouldRun($op, $objectName, $salesOrderId) {
    return strtolower($objectName) == 'contribution' && !empty($salesOrderId) && $op == 'create';
  }

}
