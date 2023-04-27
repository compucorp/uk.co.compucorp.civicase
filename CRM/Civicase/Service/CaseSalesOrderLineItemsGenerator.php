<?php

use Civi\Api4\LineItem;
use Civi\Api4\CaseSalesOrderContribution;
use Civi\Api4\CaseSalesOrderLine;
use Civi\Api4\CaseSalesOrder;

/**
 * Service class to generate sales order line items.
 */
class CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator {

  const INVOICE_PERCENT = 'percent';
  const INVOICE_REMAIN = 'remain';

  /**
   * The current sales order entity.
   *
   * @var array
   */
  public array $salesOrder;

  /**
   * Constructs CaseSalesOrderContribution service.
   */
  public function __construct(private int $salesOrderId, private string $type, private ?string $percentValue) {
    $this->setSalesOrder();
  }

  /**
   * Sets the sales order value.
   */
  private function setSalesOrder(): void {
    $this->salesOrder = CaseSalesOrder::get()
      ->addSelect('*')
      ->addWhere('id', '=', $this->salesOrderId)
      ->addChain('items', CaseSalesOrderLine::get()
        ->addWhere('sales_order_id', '=', '$id')
        ->addSelect('*', 'product_id.name', 'financial_type_id.name')
      )
      ->execute()
      ->first() ?? [];
  }

  /**
   * Generates Line Items for the sales order entity.
   */
  public function generateLineItems() {
    $lineItems = [];

    if (empty($this->salesOrder['items'])) {
      return [];
    }

    $lineItems = $this->getLineItemForSalesOrder();

    if ($this->type === self::INVOICE_REMAIN) {
      $lineItems = [...$lineItems, ...$this->getPreviousContributionLineItem()];
    }

    return $lineItems;
  }

  /**
   * Returns the line items for a sales order.
   *
   * @return array
   *   Array of values keyed by contribution line item fields.
   */
  private function getLineItemForSalesOrder() {
    $items = [];
    foreach ($this->salesOrder['items'] as $item) {
      $item['quantity'] = ($this->type === self::INVOICE_PERCENT) ?
      ($this->percentValue / 100) * $item['quantity'] :
      $item['quantity'];
      $item['total'] = $item['quantity'] * floatval($item['unit_price']);
      $item['tax'] = empty($item['tax_rate']) ? 0 : $this->percent($item['tax_rate'], $item['total']);

      $items[] = $this->lineItemToContributionLineItem($item);

      if ($item['discounted_percentage'] > 0) {
        $item['item_description'] = "{$item['item_description']} Discount {$item['discounted_percentage']}%";
        $item['unit_price'] = $this->percent($item['discounted_percentage'], -$item['unit_price']);
        $item['total'] = $item['quantity'] * floatval($item['unit_price']);
        $item['tax'] = empty($item['tax_rate']) ? 0 : $this->percent($item['tax_rate'], $item['total']);
        $items[] = $this->lineItemToContributionLineItem($item);
      }
    }

    return $items;
  }

  /**
   * Returns the line items for a sales order.
   *
   * This is from the previously created contributions.
   *
   * @return array
   *   Array of values keyed by contribution line item fields.
   */
  private function getPreviousContributionLineItem() {
    $previousItems = [];

    $caseSalesOrderContributions = CaseSalesOrderContribution::get()
      ->addSelect('contribution_id')
      ->addWhere('case_sales_order_id.id', '=', $this->salesOrderId)
      ->addChain('items', LineItem::get()
        ->addWhere('contribution_id', '=', '$contribution_id')
    )
      ->execute();

    foreach ($caseSalesOrderContributions as $contribution) {
      $items = $contribution['items'];

      if (empty($items)) {
        return [];
      }

      foreach ($items as $item) {
        $item['qty'] = $item['qty'];
        $item['unit_price'] = -1 * $item['unit_price'];
        $item['tax_amount'] = -1 * $item['tax_amount'];
        $item['line_total'] = $item['qty'] * floatval($item['unit_price']);
        $previousItems[] = $item;
      }
    }

    return $previousItems;
  }

  /**
   * Converts a sales order line item to a contribution line item.
   *
   * @param array $item
   *   Sales Order line item.
   *
   * @return array
   *   Contribution line item
   */
  private function lineItemToContributionLineItem(array $item) {
    return [
      'qty' => $item['quantity'],
      'tax_amount' => $item['tax'],
      'label' => $item['item_description'],
      'entity_table' => 'civicrm_contribution',
      'financial_type_id' => $item['financial_type_id'],
      'line_total' => $item['total'],
      'unit_price' => $item['unit_price'],
    ];
  }

  /**
   * Returns percentage% of value.
   *
   * E.g. 5% of 10.
   *
   * @param float $percentage
   *   Percentage to calculate.
   * @param float $value
   *   The value to get percentage of.
   *
   * @return float
   *   Calculated Percentage in float
   */
  public function percent(float $percentage, float $value) {
    return (floatval($percentage) / 100) * floatval($value);
  }

}
