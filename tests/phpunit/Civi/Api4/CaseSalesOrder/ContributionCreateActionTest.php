<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\CaseSalesOrderContribution as Api4CaseSalesOrderContribution;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;
use CRM_Civicase_Service_CaseSalesOrderContribution as CaseSalesOrderContribution;

/**
 * CaseSalesOrder.ContributionCreateAction API Test Case.
 *
 * @group headless
 */
class Civi_Api4_CaseSalesOrder_ContributionCreateActionTest extends BaseHeadlessTest {

  use Helpers_PriceFieldTrait;
  use Helpers_CaseSalesOrderTrait;
  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Setup data before tests run.
   */
  public function setUp() {
    $this->generatePriceField();
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
  }

  /**
   * Ensures contribution create action updates status successfully.
   */
  public function testContributionCreateActionWillUpdateSalesOrderStatus() {
    ['id' => $id] = $this->createCaseSalesOrder();

    $newStatus = $this->getCaseSalesOrderStatus()[1]['value'];
    CaseSalesOrder::contributionCreateAction()
      ->setId($id)
      ->setStatusId($newStatus)
      ->setToBeInvoiced('percent')
      ->setPercentValue('100')
      ->setDate('2020-2-12')
      ->setFinancialTypeId('1')
      ->execute();

    $results = CaseSalesOrder::get()
      ->addWhere('id', '=', $id)
      ->execute()
      ->first();

    $this->assertEquals($newStatus, $results['status_id']);
  }

  /**
   * Ensures The total amount of contribution will be the expected amount.
   *
   * @dataProvider provideContributionCreateData
   */
  public function testAppropriateContributionAmountIsCreated($expectedPercent, $contributionCreateData, $withDiscount = FALSE, $withTax = FALSE) {
    $params = [];

    if ($withDiscount) {
      $params['items']['discounted_percentage'] = rand(1, 50);
    }

    if ($withTax) {
      $params['items']['tax_rate'] = rand(1, 50);
    }

    $salesOrder = $this->createCaseSalesOrder($params);
    $computedTotal = CaseSalesOrder::computeTotal()
      ->setLineItems($salesOrder['items'])
      ->execute()
      ->jsonSerialize()[0];

    foreach ($contributionCreateData as $data) {
      CaseSalesOrder::contributionCreateAction()
        ->setId($salesOrder['id'])
        ->setStatusId($data['statusId'])
        ->setToBeInvoiced($data['toBeInvoiced'])
        ->setPercentValue($data['percentValue'])
        ->setDate($data['date'])
        ->setFinancialTypeId($data['financialTypeId'])
        ->execute();
    }

    $contributionAmounts = Api4CaseSalesOrderContribution::get()
      ->addSelect('contribution_id', 'contribution_id.total_amount')
      ->addWhere('case_sales_order_id.id', '=', $salesOrder['id'])
      ->execute()
      ->jsonSerialize();

    $paidTotal = array_sum(array_column($contributionAmounts, 'contribution_id.total_amount'));

    // We can only guarantee that the value will be equal to 1 decimal place.
    $this->assertEquals(round(($expectedPercent * $computedTotal['totalAfterTax']) / 100, 1), round($paidTotal, 1));
  }

  /**
   * Provides data to test contribution create action.
   *
   * @return array
   *   Array of different scenarios
   */
  public function provideContributionCreateData(): array {
    return [
      '100% value will be total of the sales order value' => [
        'expectedPercent' => 100,
        'contributionCreateData' => [
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 100,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
        ],
      ],
      '100% value will be total of the sales order value with discount applied' => [
        'expectedPercent' => 100,
        'contributionCreateData' => [
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 100,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
        ],
        'withDiscount' => TRUE,
      ],
      '100% value will be total of the sales order value with tax_rate applied' => [
        'expectedPercent' => 100,
        'contributionCreateData' => [
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 100,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
        ],
        'withTax' => TRUE,
      ],
      '100% value will be total of the sales order value with tax_rate applied and paid twice' => [
        'expectedPercent' => 100,
        'contributionCreateData' => [
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 50,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 50,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
        ],
        'withTax' => TRUE,
      ],
      '100% value will be total of the sales order value when paid in 4 instalment of 25%' => [
        'expectedPercent' => 100,
        'contributionCreateData' => [
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 25,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 25,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 25,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 25,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
        ],
      ],
      '75% value will be total of the sales order value when paid in 3 instalment of 25%' => [
        'expectedPercent' => 75,
        'contributionCreateData' => [
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 25,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 25,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 25,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
        ],
      ],
      '100% value will be total of the sales order value when paid at once using remain option' => [
        'expectedPercent' => 100,
        'contributionCreateData' => [
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_REMAIN,
      // Expects this value to be ignored.
            'percentValue' => 25,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
        ],
      ],
      '100% value will be total of the sales order value when paid with 25% and remain option' => [
        'expectedPercent' => 100,
        'contributionCreateData' => [
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 25,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_REMAIN,
            'percentValue' => 0,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
        ],
      ],
      '100% value will be total of the sales order value when paid with 30%, 30% and remain option' => [
        'expectedPercent' => 100,
        'contributionCreateData' => [
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 30,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
            'percentValue' => 30,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
          [
            'statusId' => 1,
            'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_REMAIN,
            'percentValue' => 0,
            'date' => date("Y-m-d"),
            'financialTypeId' => '1',
          ],
        ],
      ],
    ];
  }

}
