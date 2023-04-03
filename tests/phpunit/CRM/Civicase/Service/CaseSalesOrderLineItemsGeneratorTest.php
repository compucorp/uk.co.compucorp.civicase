<?php

use Civi\Api4\CaseSalesOrder;
use CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator as SalesOrderService;

/**
 * Runs tests on CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator Service tests.
 *
 * @group headless
 */
class CRM_Civicase_Service_CaseSalesOrderLineItemsGeneratorsTest extends BaseHeadlessTest {
  use Helpers_PriceFieldTrait;
  use Helpers_CaseSalesOrderTrait;

  /**
   * Setup data before tests run.
   */
  public function setUp() {
    $this->generatePriceField();
  }

  /**
   * Ensures the correct number of line item is created.
   *
   * When there's no previous contribution.
   */
  public function testCorrectNumberOfLineItemsIsGeneratedWithoutPreviousContribution() {
    $salesOrder = $this->createCaseSalesOrder();

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_PERCENT, 25);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(2, $lineItems);
  }

  /**
   * Ensures the correct number of line item is created.
   *
   * When there's previous contribution.
   */
  public function testCorrectNumberOfLineItemsIsGeneratedWithPreviousContribution() {
    $salesOrder = $this->createCaseSalesOrder();

    $previousContributionCount = rand(1, 4);
    for ($i = 0; $i < $previousContributionCount; $i++) {
      CaseSalesOrder::contributionCreateAction()
        ->setIds([$salesOrder['id']])
        ->setStatusId(1)
        ->setToBeInvoiced(SalesOrderService::INVOICE_PERCENT)
        ->setPercentValue(20)
        ->setDate(date('Y-m-d'))
        ->setFinancialTypeId('1')
        ->execute();
    }

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_REMAIN, 0);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(($previousContributionCount * 2) + 2, $lineItems);
  }

}
