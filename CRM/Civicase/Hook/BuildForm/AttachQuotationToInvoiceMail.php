<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contribution;

/**
 * Gives user the option to attach quotation to invoice mail.
 */
class CRM_Civicase_Hook_BuildForm_AttachQuotationToInvoiceMail {

  /**
   * Adds the Attach Quote checkbox to invoice mail form.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   * @param string $formName
   *   Form Name.
   */
  public function run(CRM_Core_Form &$form, $formName) {
    if (!$this->shouldRun($form, $formName)) {
      return;
    }

    $form->add('checkbox', 'attach_quote', ts('Attach Quotation'));

    CRM_Core_Region::instance('page-body')->add([
      'template' => "CRM/Civicase/Form/Contribute/AttachQuotation.tpl",
    ]);
  }

  /**
   * Determines if the hook will run.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   * @param string $formName
   *   Form Name.
   *
   * @return bool
   *   TRUE if the hook should run, FALSE otherwise.
   */
  private function shouldRun($form, $formName) {
    if ($formName != 'CRM_Contribute_Form_Task_Invoice') {
      return FALSE;
    }

    $contributionId = CRM_Utils_Request::retrieve('id', 'Positive', $form, FALSE);
    if (!$contributionId) {
      return FALSE;
    }

    $salesOrder = Contribution::get()
      ->addSelect('Opportunity_Details.Quotation')
      ->addWhere('Opportunity_Details.Quotation', 'IS NOT EMPTY')
      ->addWhere('id', 'IN', explode(',', $contributionId))
      ->addChain('salesOrder', CaseSalesOrder::get()
        ->addWhere('id', '=', '$Opportunity_Details.Quotation')
      )
      ->execute()
      ->getArrayCopy();

    return !empty($salesOrder);
  }

}
