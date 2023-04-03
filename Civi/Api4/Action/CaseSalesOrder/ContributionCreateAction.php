<?php

namespace Civi\Api4\Action\CaseSalesOrder;

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\PriceFieldValue;
use Civi\Api4\PriceField;
use Civi\Api4\PriceSet;
use CRM_Core_Transaction;
use Civi\Api4\Generic\Result;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Traits\DAOActionTrait;
use CRM_Contribute_BAO_Contribution as Contribution;
use CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator as salesOrderlineItemGenerator;
use Civi\Api4\CaseSalesOrderContribution as Api4CaseSalesOrderContribution;

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
  protected $salesOrderIds;

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
    $priceField = $this->getDefaultContributionPriceField();

    foreach ($this->salesOrderIds as $id) {
      try {
        $contribution = $this->createContributionWithLineItems($id, $priceField);
        $this->linkCaseSalesOrderToContribution($id, $contribution['id']);
        $this->updateCaseSalesOrderStatus($id);
      }
      catch (\Exception $e) {
        $transaction->rollback();
      }
    }

    return [];
  }

  /**
   * Creates sales order contribution with associated line items.
   *
   * @param int $salesOrderId
   *   Sales Order ID.
   * @param array $priceField
   *   Array of price fields.
   */
  public function createContributionWithLineItems(int $salesOrderId, array $priceField): array {
    $salesOrderContribution = new salesOrderlineItemGenerator($salesOrderId, $this->toBeInvoiced, $this->percentValue);
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
      'source' => "Quotation {$salesOrderId}",
      'line_item' => $allLineItems,
      'total_amount' => $totalAmount,
      'tax_amount' => $taxAmount,
      'financial_type_id' => $this->financialTypeId,
      'receive_date' => $this->date,
      'contact_id' => $salesOrderContribution->salesOrder['client_id'],
    ];

    return Contribution::create($params)->toArray();
  }

  /**
   * Returns default contribution price set fields.
   *
   * @return array
   *   Array of price fields
   */
  public function getDefaultContributionPriceField(): array {
    $priceSet = PriceSet::get()
      ->addWhere('name', '=', 'default_contribution_amount')
      ->addWhere('is_quick_config', '=', 1)
      ->execute()
      ->first();

    return PriceField::get()
      ->addWhere('price_set_id', '=', $priceSet['id'])
      ->addChain('price_field_value', PriceFieldValue::get()
        ->addWhere('price_field_id', '=', '$id')
      )->execute()
      ->getArrayCopy();
  }

  /**
   * Links sales order with contirbution.
   *
   * This is done by inserting new row into the
   * pivot table CaseSalesOrderContribution.
   *
   * @param int $salesOrderId
   *   Sales Order Id.
   * @param int $contributionId
   *   Contribution ID.
   */
  public function linkCaseSalesOrderToContribution(int $salesOrderId, int $contributionId): void {
    Api4CaseSalesOrderContribution::create()
      ->addValue('case_sales_order_id', $salesOrderId)
      ->addValue('to_be_invoiced', $this->toBeInvoiced)
      ->addValue('percent_value', $this->percentValue)
      ->addValue('contribution_id', $contributionId)
      ->execute();
  }

  /**
   * Updates Sales Order status.
   *
   * @param int $salesOrderId
   *   Sales Order Id.
   */
  public function updateCaseSalesOrderStatus(int $salesOrderId): void {
    CaseSalesOrder::update()
      ->addWhere('id', '=', $salesOrderId)
      ->addValue('status_id', $this->statusId)
      ->execute();
  }

}
