<?php

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
      'percent', [
        'id' => 'invoice_percent',
      ]);
    $this->add('text', 'percent_value', '', [
      'id' => 'percent_value',
      'placeholder' => 'Percentage to be invoiced',
      'class' => 'form-control',
      'min' => 1,
      'max' => 10,
    ], FALSE);
    $this->addElement('radio', 'to_be_invoiced', '', ts('Remaining Balance'),
      'remain',
      ['id' => 'invoice_remain']
    );
    $this->addRule('to_be_invoiced', ts('Invoice value is required'), 'required');

    $statusOptions = OptionValue::get()
      ->addSelect('value', 'label')
      ->addWhere('option_group_id:name', '=', 'case_sales_order_status')
      ->execute()
      ->getArrayCopy();
    array_combine(array_column($statusOptions, 'label'), array_column($statusOptions, 'value'));

    $this->add(
      'select',
      'status',
      ts('Status'),
      array_merge(
        ['' => 'Select'],
        array_combine(
          array_column($statusOptions, 'value'),
          array_column($statusOptions, 'label')
        ),
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
  public function addRules() {
    $this->addFormRule([$this, 'formRule']);
  }

  /**
   * Form Validation rule.
   *
   * This enforces the rule whereby,
   * user must supply an amount if the
   * enter percentage smount radio is selected.
   *
   * @param array $values
   *   Array of submitted values.
   *
   * @return array|bool
   *   Returns the form errors if form is invalid
   */
  public function formRule(array $values) {
    $errors = [];

    if ($values['to_be_invoiced'] == 'percent' && empty(floatval($values['percent_value']))) {
      $errors['percent_value'] = 'Percentage value is required';
    }

    return $errors ?: TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function postProcess() {
    $values = $this->getSubmitValues();

    if (!empty($this->id) && $values['to_be_invoiced'] == 'percent') {
      $this->createPercentageContribution($values);
    }
  }

  /**
   * Redirects user to contribution add page.
   *
   * This contribution page will have the line items
   * prefilled from the sales order line items.
   */
  public function createPercentageContribution(array $values) {
    $query = [
      'action' => 'add',
      'reset' => 1,
      'context' => 'standalone',
      'sales_order' => $this->id,
      'sales_order_status_id' => $values['status'],
      'percent_amount' => floatval($values['percent_value']),
    ];

    $url = CRM_Utils_System::url('civicrm/contribute/add', $query);
    CRM_Utils_System::redirect($url);
  }

}
