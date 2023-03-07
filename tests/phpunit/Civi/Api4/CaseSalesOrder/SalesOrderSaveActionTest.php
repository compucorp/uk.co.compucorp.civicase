<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\CaseSalesOrderLine;
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

  /**
   * Test Case Sales Order save action updates sales order as expected.
   */
  public function testCaseSalesOrderIsUpdatedWithSaveAction() {
    $salesOrderData = $this->getCaseSalesOrderData();
    $salesOrderData['items'][] = $this->getCaseSalesOrderLineData();
    $salesOrderData['items'][] = $this->getCaseSalesOrderLineData();

    // Create sales order.
    $salesOrder = CaseSalesOrder::save()
      ->addRecord($salesOrderData)
      ->execute()
      ->jsonSerialize()[0];

    // Update Sales order.
    $salesOrderData['id'] = $salesOrder['id'];
    $salesOrderData['items'] = $salesOrder['items'];
    $salesOrderData['notes'] = substr(md5(mt_rand()), 0, 7);
    $salesOrderData['description'] = substr(md5(mt_rand()), 0, 7);
    CaseSalesOrder::save()
      ->addRecord($salesOrderData)
      ->execute()
      ->jsonSerialize()[0];

    // Assert that sales order was updated.
    $updatedSalesOrder = CaseSalesOrder::get()
      ->addWhere('id', '=', $salesOrder['id'])
      ->execute()
      ->jsonSerialize()[0];

    $this->assertEquals($salesOrderData['id'], $updatedSalesOrder['id']);
    $this->assertEquals($salesOrderData['notes'], $updatedSalesOrder['notes']);
    $this->assertEquals($salesOrderData['description'], $updatedSalesOrder['description']);
  }

  /**
   * Test line items not included in case sales order update data are removed.
   */
  public function testDetachedLineItemsAreRemovedFromSalesOrderOnUpdate() {
    $salesOrderData = $this->getCaseSalesOrderData();
    $salesOrderData['items'][] = $this->getCaseSalesOrderLineData();
    $salesOrderData['items'][] = $this->getCaseSalesOrderLineData();

    $salesOrder = CaseSalesOrder::save()
      ->addRecord($salesOrderData)
      ->execute()
      ->jsonSerialize()[0];

    $this->assertCount(2, $salesOrder['items']);

    // Perform update with single line item.
    $salesOrderData['id'] = $salesOrder['id'];
    $salesOrderData['items'] = [$salesOrder['items'][0]];
    CaseSalesOrder::save()
      ->addRecord($salesOrderData)
      ->execute()
      ->jsonSerialize()[0];

    // Assert that sales order has only one line item.
    $salesOrderLine = CaseSalesOrderLine::get()
      ->addWhere('sales_order_id', '=', $salesOrder['id'])
      ->execute()
      ->jsonSerialize();

    $this->assertCount(1, $salesOrderLine);
    $this->assertEquals($salesOrder['items'][0]['id'], $salesOrderLine[0]['id']);
  }

}
