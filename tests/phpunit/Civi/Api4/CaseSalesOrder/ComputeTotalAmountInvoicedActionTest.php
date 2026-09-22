<?php

use Civi\Api4\CaseSalesOrder;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * CaseSalesOrder.ComputeTotalAmountInvoicedAction API Test Case.
 *
 * @group headless
 */
class Civi_Api4_CaseSalesOrder_ComputeTotalAmountInvoicedActionTest extends BaseHeadlessTest {

  use Helpers_PriceFieldTrait;
  use Helpers_CaseSalesOrderTrait;
  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Setup data before tests run.
   */
  public function setUp(): void {
    $this->generatePriceField();
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
  }

  /**
   * Ensures the action is reachable under its documented parameter name.
   */
  public function testActionAcceptsTheSalesOrderIdParameterName() {
    $salesOrder = $this->createCaseSalesOrder();

    $result = civicrm_api4('CaseSalesOrder', 'computeTotalAmountInvoiced', [
      'salesOrderId' => $salesOrder['id'],
      'checkPermissions' => FALSE,
    ]);

    $this->assertArrayHasKey('amount', (array) $result);
  }

  /**
   * Ensures the parameter is exposed in the action's metadata.
   */
  public function testSalesOrderIdIsKnownApiParameter() {
    $action = CaseSalesOrder::computeTotalAmountInvoiced();

    $this->assertTrue($action->paramExists('salesOrderId'));
    $this->assertArrayHasKey('salesOrderId', $action->getParamInfo());
  }

  /**
   * Ensures the chain used by the quotation view page resolves successfully.
   */
  public function testActionCanBeChainedFromSalesOrderGet() {
    $salesOrder = $this->createCaseSalesOrder();

    $result = civicrm_api4('CaseSalesOrder', 'get', [
      'checkPermissions' => FALSE,
      'where' => [['id', '=', $salesOrder['id']]],
      'limit' => 1,
      'chain' => [
        'totalAmountInvoiced' => [
          'CaseSalesOrder',
          'computeTotalAmountInvoiced',
          ['salesOrderId' => '$id'],
        ],
      ],
    ])->first();

    $this->assertArrayHasKey('totalAmountInvoiced', $result);
    $this->assertArrayHasKey('amount', $result['totalAmountInvoiced']);
  }

  /**
   * Ensures nothing is invoiced against a quotation with no contributions.
   */
  public function testActionReturnsZeroWhenNoContributionsExist() {
    $salesOrder = $this->createCaseSalesOrder();

    $result = CaseSalesOrder::computeTotalAmountInvoiced()
      ->setSalesOrderId($salesOrder['id'])
      ->execute();

    $this->assertEquals(0.0, $result['amount']);
  }

  /**
   * Ensures the full quotation total is returned once fully invoiced.
   */
  public function testActionReturnsTheInvoicedAmount() {
    $salesOrder = $this->createCaseSalesOrder();

    CaseSalesOrder::contributionCreateAction()
      ->setSalesOrderIds([$salesOrder['id']])
      ->setStatusId($this->getCaseSalesOrderStatus()[1]['value'])
      ->setToBeInvoiced('percent')
      ->setPercentValue('100')
      ->setDate('2020-2-12')
      ->setFinancialTypeId('1')
      ->execute();

    $expected = CaseSalesOrder::get(FALSE)
      ->addSelect('total_after_tax')
      ->addWhere('id', '=', $salesOrder['id'])
      ->execute()
      ->first()['total_after_tax'];

    $result = CaseSalesOrder::computeTotalAmountInvoiced()
      ->setSalesOrderId($salesOrder['id'])
      ->execute();

    $this->assertEquals(round((float) $expected, 2), round((float) $result['amount'], 2));
  }

  /**
   * Ensures an omitted sales order ID returns empty rather than fatalling.
   */
  public function testActionReturnsEmptyResultWhenSalesOrderIdIsOmitted() {
    $result = CaseSalesOrder::computeTotalAmountInvoiced()->execute();

    $this->assertArrayNotHasKey('amount', (array) $result);
  }

}
