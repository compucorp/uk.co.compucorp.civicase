<?php

use Civi\Api4\OptionValue;
use CRM_Civicase_Test_Fabricator_Case as CaseFabricator;
use CRM_Civicase_Test_Fabricator_Product as ProductFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;

/**
 * Case sales order helper trait.
 */
trait Helpers_CaseSalesOrderTrait {

  /**
   * Returns list of available statuses.
   *
   * @return array
   *   Array of sales order statuses
   */
  public function getCaseSalesOrderStatus() {
    $salesOrderStatus = OptionValue::get()
      ->addSelect('id', 'value', 'name', 'label')
      ->addWhere('option_group_id:name', '=', 'case_sales_order_status')
      ->execute();

    return $salesOrderStatus;
  }

  /**
   * Returns fabricated case sales order data.
   *
   * @param array $default
   *   Default value.
   *
   * @return array
   *   Key-Value pair of a case sales order fields and values
   */
  public function getCaseSalesOrderData(array $default = []) {
    $client = ContactFabricator::fabricate();
    $caseType = CaseTypeFabricator::fabricate();
    $case = CaseFabricator::fabricate(
      [
        'case_type_id' => $caseType['id'],
        'contact_id' => $client['id'],
        'creator_id' => $client['id'],
      ]
    );

    return array_merge([
      'client_id' => $client['id'],
      'owner_id' => $client['id'],
      'case_id' => $case['id'],
      'currency' => 'GBP',
      'status_id' => $this->getCaseSalesOrderStatus()[0]['value'],
      'description' => 'test',
      'notes' => 'test',
      'total_before_tax' => 0,
      'total_after_tax' => 0,
      'quotation_date' => '2022-08-09',
      'items' => [],
    ], $default);
  }

  /**
   * Returns fabricated case sales order line data.
   *
   * @param array $default
   *   Default value.
   *
   * @return array
   *   Key-Value pair of a case sales order line item fields and values
   */
  public function getCaseSalesOrderLineData(array $default = []) {
    $product = ProductFabricator::fabricate();
    return array_merge([
      'financial_type_id' => 1,
      'product_id' => $product['id'],
      'item_description' => 'test',
      'quantity' => 1,
      'unit_price' => 50,
      'tax_rate' => NULL,
      'discounted_percentage' => NULL,
      'subtotal_amount' => 50,
    ], $default);
  }

}
