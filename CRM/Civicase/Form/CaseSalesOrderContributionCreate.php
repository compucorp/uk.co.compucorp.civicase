<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\CaseSalesOrderLine;
use Civi\Api4\OptionValue;
use CRM_Certificate_ExtensionUtil as E;

/**
 * Case sales order contribution create Form controller class.
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Civicase_Form_CaseSalesOrderContributionCreate extends CRM_Core_Form {

  /**
   * Case sales order to delete.
   *
   * @var int
   */
  public $id;

  const INVOICE_PERCENT = 'percent';
  const INVOICE_REMAIN = 'remain';

  /**
   * {@inheritDoc}
   */
  public function preProcess() {
    CRM_Utils_System::setTitle('Create Contribution');

    $this->id = CRM_Utils_Request::retrieve('id', 'Positive', $this);
  }

  /**
   * {@inheritDoc}
   */
  public function buildQuickForm() {
    $this->addElement('radio', 'to_be_invoiced', '', ts('Enter % to be invoiced ?'),
      self::INVOICE_PERCENT, [
        'id' => 'invoice_percent',
      ]);
    $this->add('text', 'percent_value', '', [
      'id' => 'percent_value',
      'placeholder' => 'Percentage to be invoiced',
      'class' => 'form-control',
      'min' => 1,
    ], FALSE);

    $caseSalesOrderLines = CaseSalesOrderLine::get(FALSE)
      ->addWhere('sales_order_id', '=', $this->id)
      ->execute();
    $productIds = [];
    foreach ($caseSalesOrderLines as $line) {
      if (!empty($line['product_id'])) {
        array_push($productIds, $line['product_id']);
      }
    }

    $this->addEntityRef('products', ts('Products'), [
      'entity' => 'Product',
      'placeholder' => 'All Products',
      'class' => 'form-control',
      'select' => ['minimumInputLength' => 0, 'multiple' => TRUE],
      'api' => ['params' => ['id' => ['IN' => $productIds]]],
    ]);

    if ($this->hasRemainingBalance()) {
      $this->addElement('radio', 'to_be_invoiced', '', ts('Remaining Balance'),
        self::INVOICE_REMAIN,
        ['id' => 'invoice_remain']
      );
      $this->addRule('to_be_invoiced', ts('Invoice value is required'), 'required');
    }

    $statusOptions = OptionValue::get(FALSE)
      ->addSelect('value', 'label')
      ->addWhere('option_group_id:name', '=', 'case_sales_order_status')
      ->execute()
      ->getArrayCopy();

    $this->add(
      'select',
      'status',
      ts('Update status of quotation to'),
        ['' => 'Select'] +
        array_combine(
          array_column($statusOptions, 'value'),
          array_column($statusOptions, 'label')
        ),
      TRUE,
      ['class' => 'form-control']
    );

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Create Contribution'),
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
        // 'isDefault' => TRUE,
      ],
    ]);

    parent::buildQuickForm();
  }

  /**
   * {@inheritDoc}
   */
  public function setDefaultValues() {
    $caseSalesOrder = CaseSalesOrder::get(FALSE)
      ->addWhere('id', '=', $this->id)
      ->addSelect('status_id')
      ->execute()
      ->first();

    return [
      'status' => $caseSalesOrder['status_id'] ?? NULL,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function addRules() {
    $this->addFormRule([$this, 'formRule']);
    $this->addFormRule([$this, 'validateAmount']);
  }

  /**
   * Form Validation rule.
   *
   * This enforces the rule whereby,
   * user must supply an amount if the
   * enter percentage amount radio is selected.
   *
   * @param array $values
   *   Array of submitted values.
   *
   * @return array|bool
   *   Returns the form errors if form is invalid
   */
  public function formRule(array $values) {
    $errors = [];

    if ($values['to_be_invoiced'] == self::INVOICE_PERCENT) {
      $percentValue = floatval($values['percent_value']);
      if (empty($percentValue)) {
        $errors['percent_value'] = 'Percentage value is required';
      }
      elseif ($percentValue < 1) {
        $errors['percent_value'] = 'Percentage value must be at least 1';
      }
    }

    return $errors ?: TRUE;
  }

  /**
   * Validate Invoice value.
   *
   * Ensures that percent amount entered by user or
   * calculated as part of other remaining balance
   * selection is correct and  not exceeding the
   * balance amount.
   *
   * e.g. If a sales_order total_amount is 1000,
   * and has  the following contributions
   * contribution 1 with value - 500
   * contribution 2 with value - 250
   * the amount owed is 250, so any new contribution
   * that will exceed this amount should return an error.
   *
   * @param array $values
   *   Array of submitted values.
   *
   * @return array|bool
   *   Returns the form errors if form is invalid
   */
  public function validateAmount(array $values) {
    $errors = [];
    $remainingBalance = $this->getRemainingBalance();

    if ($values['to_be_invoiced'] == self::INVOICE_PERCENT) {
      if ($remainingBalance <= 0) {
        $errors['percent_value'] = 'This quotation has already been invoiced in full.';

        return $errors;
      }

      $invoiceTotal = $this->getGeneratedInvoiceTotal($values);
      if ($invoiceTotal <= 0) {
        $errors['percent_value'] = 'This percentage is too small to invoice anything against this quotation. Please enter a larger percentage.';
      }
      elseif ($invoiceTotal > $remainingBalance) {
        $errors['percent_value'] = sprintf(
          'Invoicing this percentage would come to %s, which is more than the %s left on this quotation. Enter a smaller percentage, or use Remaining Balance to invoice the rest.',
          CRM_Utils_Money::format($invoiceTotal),
          CRM_Utils_Money::format($remainingBalance)
        );
      }

      return $errors ?: TRUE;
    }

    if ($remainingBalance <= 0) {
      $errors['to_be_invoiced'] = 'Unable to create a contribution due to insufficient balance.';
    }

    return $errors ?: TRUE;
  }

  /**
   * Returns what the submitted percentage would actually invoice.
   *
   * @param array $values
   *   Array of submitted values.
   *
   * @return float
   *   The total, tax included.
   */
  private function getGeneratedInvoiceTotal(array $values) {
    $products = array_filter(explode(',', $values['products'] ?? ''));
    $generator = new CRM_Civicase_Service_CaseSalesOrderLineItemsGenerator(
      $this->id,
      self::INVOICE_PERCENT,
      $values['percent_value'],
      $products
    );

    $total = 0;
    foreach ($generator->generateLineItems() as $lineItem) {
      $total += (float) $lineItem['line_total'] + (float) ($lineItem['tax_amount'] ?? 0);
    }

    return round($total, 2);
  }

  /**
   * Checks if the sales order has left over balance to be invoiced.
   */
  public function hasRemainingBalance() {
    return $this->getRemainingBalance() > 0;
  }

  /**
   * Returns the amount still to be invoiced against this quotation.
   *
   * @return float
   *   The outstanding balance.
   */
  public function getRemainingBalance() {
    $caseSalesOrder = CaseSalesOrder::get(FALSE)
      ->addSelect('id')
      ->addWhere('id', '=', $this->id)
      ->setLimit(1)
      ->execute()
      ->first();
    if (empty($caseSalesOrder)) {
      throw new CRM_Core_Exception("The specified case sales order doesn't exist");
    }

    $calculator = new CRM_Civicase_Service_CaseSalesOrderContributionCalculator($this->id);

    return $calculator->getRemainingBalance();
  }

  /**
   * {@inheritDoc}
   */
  public function postProcess() {
    $values = $this->getSubmitValues();

    if (!empty($this->id) && !empty($values['to_be_invoiced'])) {
      $this->createContribution($values);
    }
  }

  /**
   * Redirects user to contribution add page.
   *
   * This contribution page will have the line items
   * prefilled from the sales order line items.
   */
  public function createContribution(array $values) {
    $query = [
      'action' => 'add',
      'reset' => 1,
      'context' => 'standalone',
      'sales_order' => $this->id,
      'sales_order_status_id' => $values['status'],
      'to_be_invoiced' => $values['to_be_invoiced'],
      'percent_value' => $values['to_be_invoiced'] ==
        self::INVOICE_PERCENT ? floatval($values['percent_value']) : 0,
      'products' => $values['products'],
    ];

    $url = CRM_Utils_System::url('civicrm/contribute/add', $query);
    CRM_Utils_System::redirect($url);
  }

}
