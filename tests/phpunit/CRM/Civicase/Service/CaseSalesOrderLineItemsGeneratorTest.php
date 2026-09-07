<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;
use Civi\Api4\LineItem;
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

  /**
   * Ensures a fractional invoiced quantity stays internally consistent.
   */
  public function testPercentInvoicingOfFractionalQuantityIsInternallyConsistent() {
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder['items'][] = $this->getCaseSalesOrderLineData([
      'quantity' => 2.35,
      'unit_price' => 100,
      'subtotal_amount' => 235,
    ]);
    $salesOrder['id'] = CaseSalesOrder::save()
      ->addRecord($salesOrder)
      ->execute()
      ->jsonSerialize()[0]['id'];

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_PERCENT, 33, []);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(1, $lineItems);
    $this->assertEquals(0.78, $lineItems[0]['qty']);
    $this->assertEquals(78, $lineItems[0]['line_total']);
    $this->assertLineItemIsInternallyConsistent($lineItems[0]);
  }

  /**
   * Ensures a discount that does not land on a round penny stays consistent.
   */
  public function testFractionalDiscountIsInternallyConsistent() {
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder['items'][] = $this->getCaseSalesOrderLineData([
      'quantity' => 7,
      'unit_price' => 28.99,
      'discounted_percentage' => 15,
      'subtotal_amount' => 202.93,
    ]);
    $salesOrder['id'] = CaseSalesOrder::save()
      ->addRecord($salesOrder)
      ->execute()
      ->jsonSerialize()[0]['id'];

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_REMAIN, 0, []);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(2, $lineItems);
    foreach ($lineItems as $lineItem) {
      $this->assertLineItemIsInternallyConsistent($lineItem);
    }

    usort($lineItems, fn($a, $b) => $a['line_total'] <=> $b['line_total']);
    $this->assertEquals(-4.35, $lineItems[0]['unit_price']);
    $this->assertEquals(-30.45, $lineItems[0]['line_total']);
  }

  /**
   * Ensures rounding the quantity does not disturb whole number quantities.
   */
  public function testPercentInvoicingOfWholeNumbersIsUnaffectedByRounding() {
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder['items'][] = $this->getCaseSalesOrderLineData([
      'quantity' => 4,
      'unit_price' => 250,
      'subtotal_amount' => 1000,
    ]);
    $salesOrder['id'] = CaseSalesOrder::save()
      ->addRecord($salesOrder)
      ->execute()
      ->jsonSerialize()[0]['id'];

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_PERCENT, 25, []);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(1, $lineItems);
    $this->assertEquals(1, $lineItems[0]['qty']);
    $this->assertEquals(250, $lineItems[0]['unit_price']);
    $this->assertEquals(250, $lineItems[0]['line_total']);
    $this->assertLineItemIsInternallyConsistent($lineItems[0]);
  }

  /**
   * Ensures the remaining balance reverses what was actually invoiced.
   */
  public function testRemainingBalanceReversesTheInvoicedAmountNotRecalculatedOne() {
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder['items'][] = $this->getCaseSalesOrderLineData([
      'quantity' => 0.87,
      'unit_price' => 900,
      'subtotal_amount' => 783,
    ]);
    $salesOrder['id'] = CaseSalesOrder::save()
      ->addRecord($salesOrder)
      ->execute()
      ->jsonSerialize()[0]['id'];

    // Invoice 33%, which stores a quantity of 0.29 against a unit price of 900.
    CaseSalesOrder::contributionCreateAction()
      ->setSalesOrderIds([$salesOrder['id']])
      ->setStatusId(1)
      ->setToBeInvoiced(SalesOrderService::INVOICE_PERCENT)
      ->setPercentValue(33)
      ->setDate(date('Y-m-d'))
      ->setFinancialTypeId('1')
      ->execute();

    $invoicedAmount = 258.39;
    $contribution = Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('Opportunity_Details.Quotation', '=', $salesOrder['id'])
      ->execute()
      ->first();
    LineItem::update(FALSE)
      ->addWhere('contribution_id', '=', $contribution['id'])
      ->addValue('line_total', $invoicedAmount)
      ->execute();
    Contribution::update(FALSE)
      ->addWhere('id', '=', $contribution['id'])
      ->addValue('total_amount', $invoicedAmount)
      ->execute();

    $salesOrderService = new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_REMAIN, 0, []);
    $lineItems = $salesOrderService->generateLineItems();

    $this->assertCount(2, $lineItems);
    usort($lineItems, fn($a, $b) => $a['line_total'] <=> $b['line_total']);

    // The reversal credits back the amount that was invoiced, not 0.29 * 900.
    $this->assertEquals(-1 * $invoicedAmount, $lineItems[0]['line_total'], '', 0.001);
    $this->assertEquals(783, $lineItems[1]['line_total'], '', 0.001);
    $this->assertEquals(
      783 - $invoicedAmount,
      array_sum(array_column($lineItems, 'line_total')),
      'The remaining invoice must bill the quotation total less what was already invoiced.',
      0.001
    );
  }

  /**
   * Ensures a percentage invoice is generated from the rounded quantity.
   */
  public function testPercentInvoicingUsesTheRoundedQuantity() {
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder['items'][] = $this->getCaseSalesOrderLineData([
      'quantity' => 4,
      'unit_price' => 250,
      'subtotal_amount' => 1000,
    ]);
    $salesOrder['id'] = CaseSalesOrder::save()
      ->addRecord($salesOrder)
      ->execute()
      ->jsonSerialize()[0]['id'];

    $lineItems = (new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_PERCENT, 25, []))
      ->generateLineItems();

    $this->assertCount(1, $lineItems);
    $this->assertEquals(1, $lineItems[0]['qty']);
    $this->assertEquals(250, $lineItems[0]['line_total']);
    $this->assertLineItemIsInternallyConsistent($lineItems[0]);
  }

  /**
   * Asserts a generated line item agrees with itself once stored.
   *
   * @param array $lineItem
   *   Generated contribution line item.
   */
  private function assertLineItemIsInternallyConsistent(array $lineItem) {
    $storedQty = round((float) $lineItem['qty'], 2);
    $storedUnitPrice = round((float) $lineItem['unit_price'], 2);
    $storedLineTotal = round((float) $lineItem['line_total'], 2);

    $this->assertEquals(
      $storedLineTotal,
      round($storedQty * $storedUnitPrice, 2),
      sprintf(
        'Stored line total of %s does not equal the stored quantity of %s times the stored unit price of %s.',
        $storedLineTotal,
        $storedQty,
        $storedUnitPrice
      ),
      0.001
    );
  }

}
