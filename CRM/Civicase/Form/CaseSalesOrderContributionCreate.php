<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\CaseSalesOrderContribution;
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
      'max' => 100,
    ], FALSE);
    $this->addElement('radio', 'to_be_invoiced', '', ts('Remaining Balance'),
      self::INVOICE_REMAIN,
      ['id' => 'invoice_remain']
    );
    $this->addRule('to_be_invoiced', ts('Invoice value is required'), 'required');

    $statusOptions = OptionValue::get()
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
    $caseSalesOrder = CaseSalesOrder::get()
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

    if ($values['to_be_invoiced'] == self::INVOICE_PERCENT && empty(floatval($values['percent_value']))) {
      $errors['percent_value'] = 'Percentage value is required';
    }

    if ($values['to_be_invoiced'] == self::INVOICE_PERCENT && $values['percent_value'] > 100) {
      $errors['percent_value'] = 'Percentage value cannot exceed 100 ';
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

    $caseSalesOrder = CaseSalesOrder::get()
      ->addSelect('total_after_tax')
      ->addWhere('id', '=', $this->id)
      ->setLimit(1)
      ->execute()
      ->first();
    if (empty($caseSalesOrder)) {
      throw new CRM_Core_Exception("The specified case sales order doesn't exist");
    }

    // Get all the previous contributions.
    $caseSalesOrderContributions = CaseSalesOrderContribution::get()
      ->addSelect('contribution_id', 'contribution_id.total_amount')
      ->addWhere('case_sales_order_id.id', '=', $this->id)
      ->execute()
      ->jsonSerialize();

    $paidTotal = array_sum(array_column($caseSalesOrderContributions, 'contribution_id.total_amount'));
    $paidPercent = ($paidTotal * 100) / $caseSalesOrder['total_after_tax'];
    $remainPercent = 100 - $paidPercent;

    if ($remainPercent <= 0) {
      $errors['to_be_invoiced'] = 'Contribution amount cannot exceed the total sales order amount';
    }

    if ($values['to_be_invoiced'] == self::INVOICE_PERCENT && floatval($values['percent_value']) > $remainPercent) {
      $errors['percent_value'] = 'Percentage value cannot exceed ' . round($remainPercent, 2);
    }

    return $errors ?: TRUE;
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
    ];

    $url = CRM_Utils_System::url('civicrm/contribute/add', $query);
    CRM_Utils_System::redirect($url);
  }

}
