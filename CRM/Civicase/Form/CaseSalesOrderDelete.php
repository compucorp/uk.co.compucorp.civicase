<?php

use Civi\Api4\CaseSalesOrder;
use CRM_Certificate_ExtensionUtil as E;

/**
 * Case sales order delete Form controller class.
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Civicase_Form_CaseSalesOrderDelete extends CRM_Core_Form {

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
    CRM_Utils_System::setTitle('Delete Quotation');

    $this->id = CRM_Utils_Request::retrieve('id', 'Positive', $this);
  }

  /**
   * {@inheritDoc}
   */
  public function buildQuickForm() {
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Delete'),
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
        'isDefault' => TRUE,
      ],
    ]);

    parent::buildQuickForm();
  }

  /**
   * {@inheritDoc}
   */
  public function postProcess() {
    if (!empty($this->id)) {
      CaseSalesOrder::delete()
        ->addWhere('id', '=', $this->id)
        ->execute();
      CRM_Core_Session::setStatus(E::ts('Quotation is deleted successfully.'), ts('Quotation deleted'), 'success');
    }
  }

}
