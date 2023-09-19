<?php

namespace Civi\Api4\Action\CaseSalesOrder;

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution as Api4Contribution;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\Generic\Traits\DAOActionTrait;
use Civi\Api4\OptionValue;
use Civi\Api4\PriceField;
use Civi\Api4\PriceFieldValue;
use Civi\Api4\PriceSet;
use CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator as salesOrderlineItemGenerator;
use CRM_Contribute_BAO_Contribution as Contribution;
use CRM_Core_Transaction;

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
   * @var string|int
   */
  protected $financialTypeId;

  /**
   * {@inheritDoc}
   */
  public function _run(Result $result) { // phpcs:ignore
    $resultArray = $this->createContribution();

    return $result->exchangeArray($resultArray);
  }

  /**
   * {@inheritDoc}
   */
  private function createContribution() {
    $priceField = $this->getDefaultPriceSetFields();
    $createdContributionsCount = 0;

    foreach ($this->salesOrderIds as $id) {
      $transaction = CRM_Core_Transaction::create();
      try {
        $contribution = $this->createContributionWithLineItems($id, $priceField);
        $this->linkCaseSalesOrderToContribution($id, $contribution['id']);
        $this->updateCaseSalesOrderStatus($id);
        $createdContributionsCount++;
      }
      catch (\Exception $e) {
        $transaction->rollback();
      }

      $transaction->commit();
    }

    return ['created_contributions_count' => $createdContributionsCount];
  }

  /**
   * Creates sales order contribution with associated line items.
   *
   * @param int $salesOrderId
   *   Sales Order ID.
   * @param array $priceField
   *   Array of price fields.
   */
  private function createContributionWithLineItems(int $salesOrderId, array $priceField): array {
    $salesOrderContribution = new salesOrderlineItemGenerator($salesOrderId, $this->toBeInvoiced, $this->percentValue ?? 0);
    $lineItems = $salesOrderContribution->generateLineItems();

    $taxAmount = $lineTotal = 0;
    $allLineItems = [];
    foreach ($lineItems as $index => &$lineItem) {
      $lineItem['price_field_id'] = $priceField[$index]['id'];
      $lineItem['price_field_value_id'] = $priceField[$index]['price_field_value'][0]['id'];
      $priceSetID = \CRM_Core_DAO::getFieldValue('CRM_Price_BAO_PriceField', $priceField[$index]['id'], 'price_set_id');
      $allLineItems[$priceSetID][$priceField[$index]['id']] = $lineItem;
      $taxAmount += (float) $lineItem['tax_amount'] ?? 0;
      $lineTotal += (float) $lineItem['line_total'] ?? 0;
    }
    $totalAmount = $lineTotal + $taxAmount;

    if (round($totalAmount, 2) < 1) {
      throw new \Exception("Contribution total amount must be greater than zero");
    }

    $params = [
      'source' => "Quotation {$salesOrderId}",
      'line_item' => $allLineItems,
      'total_amount' => $totalAmount,
      'tax_amount' => $taxAmount,
      'financial_type_id' => $this->financialTypeId,
      'receive_date' => $this->date,
      'contact_id' => $salesOrderContribution->salesOrder['client_id'],
      'contribution_status_id' => $this->getPendingContributionStatusId(),
    ];

    return Contribution::create($params)->toArray();
  }

  /**
   * Returns default contribution price set fields.
   *
   * @return array
   *   Array of price fields
   */
  private function getDefaultPriceSetFields(): array {
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
   * @param int $salesOrderId
   *   Sales Order Id.
   * @param int $contributionId
   *   Contribution ID.
   */
  private function linkCaseSalesOrderToContribution(int $salesOrderId, int $contributionId): void {
    $salesOrder = CaseSalesOrder::get()
      ->addWhere('id', '=', $salesOrderId)
      ->execute()
      ->first();

    Api4Contribution::update()
      ->addValue('Opportunity_Details.Case_Opportunity', $salesOrder['case_id'])
      ->addValue('Opportunity_Details.Quotation', $salesOrderId)
      ->addWhere('id', '=', $contributionId)
      ->execute();
  }

  /**
   * Updates Sales Order status.
   *
   * @param int $salesOrderId
   *   Sales Order Id.
   */
  private function updateCaseSalesOrderStatus(int $salesOrderId): void {
    CaseSalesOrder::update()
      ->addWhere('id', '=', $salesOrderId)
      ->addValue('status_id', $this->statusId)
      ->execute();
  }

  /**
   * Returns ID for pending contribution status.
   *
   * @return int
   *   pending status ID
   */
  private function getPendingContributionStatusId(): ?int {
    $pendingStatus = OptionValue::get()
      ->addSelect('value')
      ->addWhere('option_group_id:name', '=', 'contribution_status')
      ->addWhere('name', '=', 'pending')
      ->execute()
      ->first();

    if (!empty($pendingStatus)) {
      return $pendingStatus['value'];
    }

    return NULL;
  }

}
