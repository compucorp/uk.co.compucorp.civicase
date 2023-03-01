<?php

use Civi\Api4\CaseSalesOrderLine;
use Civi\Api4\CaseSalesOrder;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * CaseSalesOrder.SalesOrderSaveAction API Test Case.
 *
 * @group headless
 */
class Civi_Api4_CaseSalesOrder_SalesOrderSaveActionTest extends BaseHeadlessTest {

  use Helpers_CaseSalesOrderTrait;
  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Setup data before tests run.
   */
  public function setUp() {
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
  }

  /**
   * Test case sales order and line item can be saved with the save action.
   */
  public function testCanSaveCaseSalesOrder() {
    $salesOrder = $this->getCaseSalesOrderData();

    $salesOrderId = CaseSalesOrder::save()
      ->addRecord($salesOrder)
      ->execute()
      ->jsonSerialize()[0]['id'];

    $results = CaseSalesOrder::get()
      ->addWhere('id', '=', $salesOrderId)
      ->execute()
      ->jsonSerialize();

    $this->assertNotEmpty($results);
    foreach (['client_id', 'owner_id', 'notes', 'total_before_tax'] as $key) {
      $this->assertEquals($salesOrder[$key], $results[0][$key]);
    }
  }

  /**
   * Test case sales order and line item can be saved with the save action.
   */
  public function testCanSaveCaseSalesOrderAndLineItems() {
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder['items'][] = $this->getCaseSalesOrderLineData();
    $salesOrder['items'][] = $this->getCaseSalesOrderLineData();

    $salesOrderId = CaseSalesOrder::save()
      ->addRecord($salesOrder)
      ->execute()
      ->jsonSerialize()[0]['id'];

    $results = CaseSalesOrderLine::get()
      ->addWhere('sales_order_id', '=', $salesOrderId)
      ->execute()
      ->jsonSerialize();

    $this->assertCount(2, $results);
    foreach ($results as $result) {
      $this->assertEquals($result['sales_order_id'], $salesOrderId);
    }
  }

  /**
   * Test case sales order total is calculated appropraitely.
   */
  public function testSaveCaseSalesOrderTotalIsCorrect() {
    $salesOrderData = $this->getCaseSalesOrderData();
    $salesOrderData['items'][] = $this->getCaseSalesOrderLineData(
      ['quantity' => 10, 'unit_price' => 10, 'tax_rate' => 10]
    );
    $salesOrderData['items'][] = $this->getCaseSalesOrderLineData(
      ['quantity' => 5, 'unit_price' => 10]
    );

    $salesOrderId = CaseSalesOrder::save()
      ->addRecord($salesOrderData)
      ->execute()
      ->jsonSerialize()[0]['id'];

    $salesOrder = CaseSalesOrder::get()
      ->addWhere('id', '=', $salesOrderId)
      ->execute()
      ->jsonSerialize()[0];

    $this->assertEquals($salesOrder['total_before_tax'], 150);
    $this->assertEquals($salesOrder['total_after_tax'], 160);
  }

}
