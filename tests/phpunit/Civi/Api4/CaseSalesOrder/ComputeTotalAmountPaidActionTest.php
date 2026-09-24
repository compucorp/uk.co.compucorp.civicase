<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * CaseSalesOrder.ComputeTotalAmountPaidAction API Test Case.
 *
 * @group headless
 */
class Civi_Api4_CaseSalesOrder_ComputeTotalAmountPaidActionTest extends BaseHeadlessTest {

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

    $result = civicrm_api4('CaseSalesOrder', 'computeTotalAmountPaid', [
      'salesOrderId' => $salesOrder['id'],
      'checkPermissions' => FALSE,
    ]);

    $this->assertArrayHasKey('amount', (array) $result);
  }

  /**
   * Ensures the parameter is exposed in the action's metadata.
   */
  public function testSalesOrderIdIsKnownApiParameter() {
    $action = CaseSalesOrder::computeTotalAmountPaid();

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
        'totalAmountPaid' => [
          'CaseSalesOrder',
          'computeTotalAmountPaid',
          ['salesOrderId' => '$id'],
        ],
      ],
    ])->first();

    $this->assertArrayHasKey('totalAmountPaid', $result);
    $this->assertArrayHasKey('amount', $result['totalAmountPaid']);
  }

  /**
   * Ensures nothing is paid against a quotation with no contributions.
   */
  public function testActionReturnsZeroWhenNoContributionsExist() {
    $salesOrder = $this->createCaseSalesOrder();

    $result = CaseSalesOrder::computeTotalAmountPaid()
      ->setSalesOrderId($salesOrder['id'])
      ->execute();

    $this->assertEquals(0.0, $result['amount']);
  }

  /**
   * Ensures pending contributions do not count towards the paid amount.
   */
  public function testActionReturnsZeroWhenContributionsArePending() {
    $salesOrder = $this->createCaseSalesOrder();
    $this->invoiceInFull($salesOrder['id']);

    $result = CaseSalesOrder::computeTotalAmountPaid()
      ->setSalesOrderId($salesOrder['id'])
      ->execute();

    $this->assertEquals(0.0, $result['amount']);
  }

  /**
   * Ensures recorded payments are summed into the paid amount.
   */
  public function testActionReturnsTheRecordedPaymentAmount() {
    $salesOrder = $this->createCaseSalesOrder();
    $this->invoiceInFull($salesOrder['id']);

    $contributionId = Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('Opportunity_Details.Quotation', '=', $salesOrder['id'])
      ->execute()
      ->first()['id'];

    civicrm_api3('Payment', 'create', [
      'contribution_id' => $contributionId,
      'total_amount' => 10,
    ]);

    $result = CaseSalesOrder::computeTotalAmountPaid()
      ->setSalesOrderId($salesOrder['id'])
      ->execute();

    $this->assertEquals(10.0, round((float) $result['amount'], 2));
  }

  /**
   * Ensures an omitted sales order ID returns empty rather than fatalling.
   */
  public function testActionReturnsEmptyResultWhenSalesOrderIdIsOmitted() {
    $result = CaseSalesOrder::computeTotalAmountPaid()->execute();

    $this->assertArrayNotHasKey('amount', (array) $result);
  }

  /**
   * Creates a contribution covering the whole quotation.
   *
   * @param int $salesOrderId
   *   Sales order ID.
   */
  private function invoiceInFull($salesOrderId) {
    CaseSalesOrder::contributionCreateAction()
      ->setSalesOrderIds([$salesOrderId])
      ->setStatusId($this->getCaseSalesOrderStatus()[1]['value'])
      ->setToBeInvoiced('percent')
      ->setPercentValue('100')
      ->setDate('2020-2-12')
      ->setFinancialTypeId('1')
      ->execute();
  }

}
