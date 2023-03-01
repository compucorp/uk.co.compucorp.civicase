<?php

use Civi\Api4\CaseSalesOrder;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * CaseSalesOrder.ComputeTotalAction API Test Case.
 *
 * @group headless
 */
class Civi_Api4_CaseSalesOrder_ComputeTotalActionTest extends BaseHeadlessTest {

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
   * Test case sales order compute action returns expected fields.
   */
  public function testComputeTotalActionReturnsExpectedFields() {
    $items = [];
    $items[] = $this->getCaseSalesOrderLineData(
      ['quantity' => 10, 'unit_price' => 10, 'tax_rate' => 10]
    );
    $items[] = $this->getCaseSalesOrderLineData(
      ['quantity' => 5, 'unit_price' => 10]
    );

    $computedTotal = CaseSalesOrder::computeTotal()
      ->setLineItems($items)
      ->execute()
      ->jsonSerialize()[0];

    $this->assertArrayHasKey('taxRates', $computedTotal);
    $this->assertArrayHasKey('totalBeforeTax', $computedTotal);
    $this->assertArrayHasKey('totalAfterTax', $computedTotal);
  }

  /**
   * Test case sales order total is calculated appropraitely.
   */
  public function testComputeTotalActionReturnsExpectedTotal() {
    $items = [];
    $items[] = $this->getCaseSalesOrderLineData(
      ['quantity' => 10, 'unit_price' => 10, 'tax_rate' => 10]
    );
    $items[] = $this->getCaseSalesOrderLineData(
      ['quantity' => 5, 'unit_price' => 10]
    );

    $computedTotal = CaseSalesOrder::computeTotal()
      ->setLineItems($items)
      ->execute()
      ->jsonSerialize()[0];

    $this->assertEquals($computedTotal['totalBeforeTax'], 150);
    $this->assertEquals($computedTotal['totalAfterTax'], 160);
  }

  /**
   * Test case sales order tax rates is computed as epxected.
   */
  public function testComputeTotalActionReturnsExpectedTaxRates() {
    $items = [];
    $items[] = $this->getCaseSalesOrderLineData(
      ['quantity' => 10, 'unit_price' => 10, 'tax_rate' => 10]
    );
    $items[] = $this->getCaseSalesOrderLineData(
      ['quantity' => 5, 'unit_price' => 10, 'tax_rate' => 2]
    );

    $computedTotal = CaseSalesOrder::computeTotal()
      ->setLineItems($items)
      ->execute()
      ->jsonSerialize()[0];

    $this->assertNotEmpty($computedTotal['taxRates']);
    $this->assertCount(2, $computedTotal['taxRates']);

    // Ensure the tax rates are sorted in ascending order of rate.
    $this->assertEquals($computedTotal['taxRates'][0]['rate'], 2);
    $this->assertEquals($computedTotal['taxRates'][0]['value'], 1);
    $this->assertEquals($computedTotal['taxRates'][1]['rate'], 10);
    $this->assertEquals($computedTotal['taxRates'][1]['value'], 10);
  }

}
