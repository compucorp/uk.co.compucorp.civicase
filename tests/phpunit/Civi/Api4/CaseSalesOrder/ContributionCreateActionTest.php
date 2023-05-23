<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\CaseSalesOrderContribution as Api4CaseSalesOrderContribution;
use CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator as CaseSalesOrderContribution;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

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
  public function setUp(): void {
    $this->generatePriceField();
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
  }

  /**
   * Ensures contribution create action updates status successfully.
   */
  public function testContributionCreateActionWillUpdateSalesOrderStatus() {
    $ids = [];

    for ($i = 0; $i < rand(5, 11); $i++) {
      $ids[] = $this->createCaseSalesOrder()['id'];
    }

    $newStatus = $this->getCaseSalesOrderStatus()[1]['value'];
    CaseSalesOrder::contributionCreateAction()
      ->setSalesOrderIds($ids)
      ->setStatusId($newStatus)
      ->setToBeInvoiced('percent')
      ->setPercentValue('100')
      ->setDate('2020-2-12')
      ->setFinancialTypeId('1')
      ->execute();

    $results = CaseSalesOrder::get()
      ->addWhere('id', 'IN', $ids)
      ->execute();

    $iterator = $results->getIterator();
    while ($iterator->valid()) {
      $this->assertEquals($newStatus, $iterator->current()['status_id']);
      $iterator->next();
    }
  }

  /**
   * Ensures contribution create action will create expeted pivot row.
   *
   * I.e.
   * Inserts a new row for each of the case sales order contribution.
   */
  public function testContributionCreateActionWillInsertPivotRow() {
    $ids = [];

    for ($i = 0; $i < rand(5, 11); $i++) {
      $ids[] = $this->createCaseSalesOrder()['id'];
    }

    $newStatus = $this->getCaseSalesOrderStatus()[1]['value'];
    CaseSalesOrder::contributionCreateAction()
      ->setSalesOrderIds($ids)
      ->setStatusId($newStatus)
      ->setToBeInvoiced('percent')
      ->setPercentValue('100')
      ->setDate('2020-2-12')
      ->setFinancialTypeId('1')
      ->execute();

    $salesOrderContributions = Api4CaseSalesOrderContribution::get()
      ->addSelect('case_sales_order_id')
      ->addWhere('case_sales_order_id.id', 'IN', $ids)
      ->execute()
      ->jsonSerialize();

    $this->assertCount(count($ids), $salesOrderContributions);
    $this->assertEmpty(array_diff(array_column($salesOrderContributions, 'case_sales_order_id'), $ids));
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
        ->setSalesOrderIds([$salesOrder['id']])
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
   * Ensures The expected numbers of contributions are created for bulk action.
   *
   * @dataProvider provideBulkContributionCreateData
   */
  public function testExpectedContributionCountIsCreated($expectedCount, $contributionCreateData, $salesOrderData) {
    $salesOrders = [];

    foreach ($salesOrderData as $data) {
      $salesOrder = $this->createCaseSalesOrder();

      if ($data['previouslyInvoiced'] > 0) {
        CaseSalesOrder::contributionCreateAction()
          ->setSalesOrderIds([$salesOrder['id']])
          ->setStatusId(1)
          ->setToBeInvoiced(CaseSalesOrderContribution::INVOICE_PERCENT)
          ->setPercentValue($data['previouslyInvoiced'])
          ->setDate(date("Y-m-d"))
          ->setFinancialTypeId(1)
          ->execute();
      }

      $salesOrders[] = $salesOrder;
    }

    $result = CaseSalesOrder::contributionCreateAction()
      ->setSalesOrderIds(array_column($salesOrders, 'id'))
      ->setStatusId($contributionCreateData['statusId'])
      ->setToBeInvoiced($contributionCreateData['toBeInvoiced'])
      ->setPercentValue($contributionCreateData['percentValue'])
      ->setDate($contributionCreateData['date'])
      ->setFinancialTypeId($contributionCreateData['financialTypeId'])
      ->execute();

    $this->assertEquals($expectedCount, $result['created_contributions_count']);
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

  /**
   * Provides data to test bulk contribution create action.
   *
   * @return array
   *   Array of different scenarios
   */
  public function provideBulkContributionCreateData(): array {
    return [
      '1 percentvalue contribution is created for 1 quotaition' => [
        'expectedCount' => 1,
        'contributionCreateData' => [
          'statusId' => 1,
          'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
          'percentValue' => 100,
          'date' => date("Y-m-d"),
          'financialTypeId' => '1',
        ],
        'salesOrderData' => [
          [
            'previouslyInvoiced' => 60,
          ],
        ],
      ],
      '2 percentvalue contributions are created for 2 quotations' => [
        'expectedCount' => 2,
        'contributionCreateData' => [
          'statusId' => 1,
          'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
          'percentValue' => 100,
          'date' => date("Y-m-d"),
          'financialTypeId' => '1',
        ],
        'salesOrderData' => [
          [
            'previouslyInvoiced' => 60,
          ],
          [
            'previouslyInvoiced' => 100,
          ],
        ],
      ],
      '2 percentvalues contribution are created for 2 quotations with 100% already invoiced' => [
        'expectedCount' => 2,
        'contributionCreateData' => [
          'statusId' => 1,
          'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_PERCENT,
          'percentValue' => 100,
          'date' => date("Y-m-d"),
          'financialTypeId' => '1',
        ],
        'salesOrderData' => [
          [
            'previouslyInvoiced' => 100,
          ],
          [
            'previouslyInvoiced' => 100,
          ],
        ],
      ],
      'No remain value contribution is created for 2 quotaions with 100% already invoiced' => [
        'expectedCount' => 0,
        'contributionCreateData' => [
          'statusId' => 1,
          'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_REMAIN,
          'percentValue' => 0,
          'date' => date("Y-m-d"),
          'financialTypeId' => '1',
        ],
        'salesOrderData' => [
          [
            'previouslyInvoiced' => 100,
          ],
          [
            'previouslyInvoiced' => 100,
          ],
        ],
      ],
      '1 remain value contribution is created for 2 quotaions, where only one has remain value' => [
        'expectedCount' => 1,
        'contributionCreateData' => [
          'statusId' => 1,
          'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_REMAIN,
          'percentValue' => 0,
          'date' => date("Y-m-d"),
          'financialTypeId' => '1',
        ],
        'salesOrderData' => [
          [
            'previouslyInvoiced' => 80,
          ],
          [
            'previouslyInvoiced' => 100,
          ],
        ],
      ],
      '2 remain value contribution is created for 2 quotaions, where the two has remain value' => [
        'expectedCount' => 2,
        'contributionCreateData' => [
          'statusId' => 1,
          'toBeInvoiced' => CaseSalesOrderContribution::INVOICE_REMAIN,
          'percentValue' => 0,
          'date' => date("Y-m-d"),
          'financialTypeId' => '1',
        ],
        'salesOrderData' => [
          [
            'previouslyInvoiced' => 0,
          ],
          [
            'previouslyInvoiced' => 0,
          ],
        ],
      ],
    ];
  }

}
