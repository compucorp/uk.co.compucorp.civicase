<?php

use Civi\Api4\CaseSalesOrder;
use CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator as SalesOrderService;

/**
 * Runs tests on CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator tests.
 *
 * @group headless
 */
class CRM_Civicase_Service_CaseSalesOrderLineItemsGeneratorsTest extends BaseHeadlessTest {
  use Helpers_PriceFieldTrait;
  use Helpers_CaseSalesOrderTrait;

  /**
   * Setup data before tests run.
   */
  public function setUp(): void {
    $this->generatePriceField();
  }

  /**
   * Ensures the correct number of line item is generated.
   *
   * When there's no previous contribution.
   */
  public function testCorrectNumberOfLineItemsIsGeneratedWithoutPreviousContribution() {
    $salesOrder = $this->createCaseSalesOrder();

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_PERCENT, 25, []);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(2, $lineItems);
  }

  /**
   * Ensures the correct number of line item is generated.
   *
   * When there's previous contribution.
   */
  public function testCorrectNumberOfLineItemsIsGeneratedWithPreviousContribution() {
    $salesOrder = $this->createCaseSalesOrder();

    $previousContributionCount = rand(1, 4);
    for ($i = 0; $i < $previousContributionCount; $i++) {
      CaseSalesOrder::contributionCreateAction()
        ->setSalesOrderIds([$salesOrder['id']])
        ->setStatusId(1)
        ->setToBeInvoiced(SalesOrderService::INVOICE_PERCENT)
        ->setPercentValue(20)
        ->setDate(date('Y-m-d'))
        ->setFinancialTypeId('1')
        ->execute();
    }

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_REMAIN, 0, []);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(($previousContributionCount * 2) + 2, $lineItems);
  }

  /**
   * Ensures the correct number of line item is generated.
   *
   * When there's discount with the right value.
   */
  public function testCorrectNumberOfLineItemsIsGeneratedWithDiscount() {
    $percent = 20;
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder['items'][] = $this->getCaseSalesOrderLineData(['discounted_percentage' => $percent]);
    $salesOrder['id'] = CaseSalesOrder::save()
      ->addRecord($salesOrder)
      ->execute()
      ->jsonSerialize()[0]['id'];

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_REMAIN, 0, []);
    $lineItems = $salesOrderService->generateLineItems();

    usort($lineItems, fn($a, $b) => $a['line_total'] <=> $b['line_total']);

    $this->assertCount(2, $lineItems);
    $this->assertEquals(-1 * $salesOrder['items'][0]['subtotal_amount'] * $percent / 100, $lineItems[0]['line_total']);
  }

  /**
   * Ensures the correct number of line item is generated.
   *
   * When the discount value is zero and the value is as expected.
   */
  public function testCorrectNumberOfLineItemsIsGeneratedWhenDiscountIsZero() {
    $percent = 0;
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder['items'][] = $this->getCaseSalesOrderLineData(['discounted_percentage' => $percent]);
    $salesOrder['id'] = CaseSalesOrder::save()
      ->addRecord($salesOrder)
      ->execute()
      ->jsonSerialize()[0]['id'];

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_REMAIN, 0, []);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(1, $lineItems);
    $this->assertEquals($salesOrder['items'][0]['subtotal_amount'], $lineItems[0]['line_total']);
  }

  /**
   * Ensures the value of the generated line item is correct.
   */
  public function testGeneratedPercentLineItemHasTheAppropraiteValue() {
    $percent = rand(20, 40);
    $salesOrder = $this->createCaseSalesOrder();

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_PERCENT, $percent, []);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(2, $lineItems);
    $this->assertEquals($salesOrder['items'][0]['subtotal_amount'] * $percent / 100, $lineItems[0]['line_total']);
    $this->assertEquals($salesOrder['items'][1]['subtotal_amount'] * $percent / 100, $lineItems[1]['line_total']);
  }

}
