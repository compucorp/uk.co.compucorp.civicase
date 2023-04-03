<?php

namespace Civi\Api4\Action\CaseSalesOrder;

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\PriceFieldValue;
use Civi\Api4\PriceField;
use Civi\Api4\PriceSet;
use CRM_Core_Transaction;
use Civi\Api4\Generic\Result;
use Civi\Api4\CaseSalesOrderLine;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Traits\DAOActionTrait;
use CRM_Contribute_BAO_Contribution as Contribution;
use CRM_Civicase_Service_CaseSalesOrderContribution as CaseSalesOrderContribution;
use CRM_Utils_SQL_Insert;

/**
 * Creates contribution for multiple sales orders.
 */
class ContributionCreateAction extends AbstractAction {
  use DAOActionTrait;

  /**
   * Sales order IDs.
   *
   * @var array
   */
  protected $ids;

  /**
   * Sales order Status ID.
   *
   * @var int
   */
  protected $statusId;

  /**
   * Type of invoice (either percent or remain).
   *
   * @var string
   */
  protected $toBeInvoiced;

  /**
   * The percentage value.
   *
   * @var int
   */
  protected $percentValue;

  /**
   * Contribution Date.
   *
   * @var string
   */
  protected $date;

  /**
   * Contribution Financial Type ID.
   *
   * @var string
   */
  protected $financialTypeId;

  /**
   * {@inheritDoc}
   */
  public function _run(Result $result) { // phpcs:ignore
    $resultArray = $this->createContribution();

    $result->exchangeArray($resultArray);
  }

  /**
   * {@inheritDoc}
   */
  protected function createContribution() {
    $transaction = CRM_Core_Transaction::create();
    $salesOrderContributions = [];

    try {
      $priceSet = PriceSet::get()
        ->addWhere('name', '=', 'default_contribution_amount')
        ->addWhere('is_quick_config', '=', 1)
        ->execute()
        ->first();
      $priceField = PriceField::get()
        ->addWhere('price_set_id', '=', $priceSet['id'])
        ->addChain('price_field_value', PriceFieldValue::get()
          ->addWhere('price_field_id', '=', '$id')
        )->execute();
      foreach ($this->ids as $id) {
        $salesOrderContribution = new CaseSalesOrderContribution($id, $this->toBeInvoiced, $this->percentValue);
        $lineItems = $salesOrderContribution->generateLineItems();

        $taxAmount = $lineTotal = 0;
        $allLineItems = [];
        foreach ($lineItems as $index => &$lineItem) {
          $lineItem['price_field_id'] = $priceField[$index]['id'];
          $lineItem['price_field_value_id'] = $priceField[$index]['price_field_value'][0]['id'];
          $priceSetID = \CRM_Core_DAO::getFieldValue('CRM_Price_BAO_PriceField', $priceField[$index]['id'], 'price_set_id');
          $allLineItems[$priceSetID][$priceField[$index]['id']] = $lineItem;
          $taxAmount += (float) ($lineItem['tax_amount'] ?? 0);
          $lineTotal += (float) ($lineItem['line_total'] ?? 0);
        }
        $totalAmount = $lineTotal + $taxAmount;

        $params = [
          'source' => "Quotation {$id}",
          'line_item' => $allLineItems,
          'total_amount' => $totalAmount,
          'tax_amount' => $taxAmount,
          'financial_type_id' => $this->financialTypeId,
          'receive_date' => $this->date,
          'contact_id' => $salesOrderContribution->salesOrder['client_id'],
        ];

        $contribution = Contribution::create($params)->toArray();
        $salesOrderContributions[] = [
          'case_sales_order_id' => $id,
          'to_be_invoiced' => $this->toBeInvoiced,
          'percent_value' => $this->percentValue,
          'contribution_id' => $contribution['id'],
        ];
      }

      $this->postCreateAction($salesOrderContributions);
    }
    catch (\Exception $e) {
      $transaction->rollback();

      throw $e;
    }
    return [];
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

    CaseSalesOrderLine::delete()
      ->addWhere('sales_order_id', '=', $salesOrder['id'])
      ->addWhere('id', 'NOT IN', $lineItemsInUse)
      ->execute();
  }

  /**
   * Updates Sales Order status.
   *
   * Also creates SalesOrdeContribution.
   *
   * @param array $salesOrderContributions
   *   Sales Order COntribution values.
   */
  public function postCreateAction(array $salesOrderContributions) {
    $insert = CRM_Utils_SQL_Insert::into(\CRM_Civicase_BAO_CaseSalesOrderContribution::$_tableName)
      ->rows($salesOrderContributions);
    \CRM_Core_DAO::executeQuery($insert->toSQL());

    CaseSalesOrder::update()
      ->addWhere('id', 'IN', $this->ids)
      ->addValue('status_id', $this->statusId)
      ->execute();
  }

}
