<?php

use Civi\Api4\CaseSalesOrder;
use CRM_Civicase_Form_CaseSalesOrderContributionCreate as ContributionCreateForm;
use CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator as SalesOrderService;

/**
 * Runs tests on CRM_Civicase_Form_CaseSalesOrderContributionCreate.
 *
 * @group headless
 */
class CRM_Civicase_Form_CaseSalesOrderContributionCreateTest extends BaseHeadlessTest {
  use Helpers_PriceFieldTrait;
  use Helpers_CaseSalesOrderTrait;

  /**
   * Setup data before tests run.
   */
  public function setUp(): void {
    $this->generatePriceField();
  }

  /**
   * Creates a quotation of 0.87 units at 900.00, ie. a total of 783.00.
   *
   * @return array
   *   The sales order.
   */
  private function createQuotation() {
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

    return $salesOrder;
  }

  /**
   * Invoices a percentage of the given quotation.
   *
   * @param int $salesOrderId
   *   The quotation.
   * @param float $percent
   *   The percentage to invoice.
   */
  private function invoicePercent($salesOrderId, $percent) {
    CaseSalesOrder::contributionCreateAction()
      ->setSalesOrderIds([$salesOrderId])
      ->setStatusId(1)
      ->setToBeInvoiced(SalesOrderService::INVOICE_PERCENT)
      ->setPercentValue($percent)
      ->setDate(date('Y-m-d'))
      ->setFinancialTypeId('1')
      ->execute();
  }

  /**
   * Validates a percentage against a quotation.
   *
   * @param int $salesOrderId
   *   The quotation.
   * @param float $percent
   *   The percentage to invoice.
   *
   * @return array|bool
   *   The form errors, or TRUE when valid.
   */
  private function validatePercent($salesOrderId, $percent) {
    $form = new ContributionCreateForm();
    $form->id = $salesOrderId;

    return $form->validateAmount([
      'to_be_invoiced' => ContributionCreateForm::INVOICE_PERCENT,
      'percent_value' => $percent,
      'products' => '',
    ]);
  }

  /**
   * Ensures a percentage that fits inside the balance is accepted.
   */
  public function testPercentageWithinTheRemainingBalanceIsAccepted() {
    $salesOrder = $this->createQuotation();

    $this->assertTrue($this->validatePercent($salesOrder['id'], 33));
  }

  /**
   * Ensures a percentage that would exceed the balance is refused.
   */
  public function testPercentageBeyondTheRemainingBalanceIsRefused() {
    $salesOrder = $this->createQuotation();
    $this->invoicePercent($salesOrder['id'], 33);
    $this->invoicePercent($salesOrder['id'], 33);

    $errors = $this->validatePercent($salesOrder['id'], 34);

    $this->assertArrayHasKey('percent_value', $errors);
    $this->assertContains('Remaining Balance', $errors['percent_value']);
  }

  /**
   * Ensures a fully invoiced quotation refuses a further percentage.
   */
  public function testPercentageIsRefusedOnceFullyInvoiced() {
    $salesOrder = $this->createQuotation();
    $this->invoicePercent($salesOrder['id'], 100);

    $errors = $this->validatePercent($salesOrder['id'], 10);

    $this->assertArrayHasKey('percent_value', $errors);
    $this->assertContains('invoiced in full', $errors['percent_value']);
  }

  /**
   * Ensures Remaining Balance closes out a part invoiced quotation exactly.
   */
  public function testRemainingBalanceClosesOutTheQuotationExactly() {
    $salesOrder = $this->createQuotation();
    $this->invoicePercent($salesOrder['id'], 33);
    $this->invoicePercent($salesOrder['id'], 33);

    $form = new ContributionCreateForm();
    $form->id = $salesOrder['id'];
    $remaining = $form->getRemainingBalance();

    $lineItems = (new SalesOrderService($salesOrder['id'], SalesOrderService::INVOICE_REMAIN, 0, []))
      ->generateLineItems();

    $total = 0;
    foreach ($lineItems as $lineItem) {
      $total += (float) $lineItem['line_total'] + (float) ($lineItem['tax_amount'] ?? 0);
    }

    $this->assertEquals($remaining, round($total, 2), 'Remaining Balance must bill exactly what is left', 0.001);
  }

}
