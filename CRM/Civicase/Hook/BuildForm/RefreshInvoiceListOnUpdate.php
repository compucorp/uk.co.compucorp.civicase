<?php

/**
 * Refresh the invoice list upon updating an invoice.
 */
class CRM_Civicase_Hook_BuildForm_RefreshInvoiceListOnUpdate {

  /**
   * Refresh the invoice list upon updating an invoice.
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

    CRM_Core_Resources::singleton()->addScript(
      "CRM.$(function($) {
        $(\"a[target='crm-popup']\").on('crmPopupFormSuccess', function (e) {
          CRM.refreshParent(e);
        });
      });
    ");
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
    if ($formName !== 'CRM_Contribute_Form_Contribution' || $form->getAction() !== CRM_Core_Action::UPDATE || !isset($_GET['snippet'])) {
      return FALSE;
    }

    return TRUE;
  }

}
