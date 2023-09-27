<?php

use Civi\Api4\CaseContact;
use CRM_Civicase_ExtensionUtil as E;

/**
 * Converts Opportunity Details Field to Entity Reference.
 */
class CRM_Civicase_Hook_BuildForm_AddEntityReferenceToCustomField {

  /**
   * Converts Opportunity Details Field to Entity Reference.
   *
   * @param CRM_Core_Form $form
   *   Form Class object.
   * @param string $formName
   *   Form Name.
   */
  public function run(CRM_Core_Form &$form, $formName) {
    if (!$this->shouldRun($formName)) {
      return;
    }

    $customFields['case'] = [
      'name' => CRM_Core_BAO_CustomField::getCustomFieldID('Case_Opportunity', 'Opportunity_Details', TRUE),
      'entity' => 'Case',
      'placeholder' => '- Select Case/Opportunity -',
    ];

    $customFields['salesOrder'] = [
      'name' => CRM_Core_BAO_CustomField::getCustomFieldID('Quotation', 'Opportunity_Details', TRUE),
      'entity' => 'CaseSalesOrder',
      'placeholder' => '- Select Quotation -',
    ];

    \Civi::resources()->add([
      'scriptFile' => [E::LONG_NAME, 'js/contribution-entityref-field.js'],
      'region' => 'page-header',
    ]);

    $this->populateDefaultFields($form, $customFields);
    \Civi::resources()->addVars('civicase', ['entityRefCustomFields' => $customFields]);
  }

  /**
   * Populates default fields.
   *
   * @param \CRM_Core_Form &$form
   *   Form Class object.
   * @param array &$customFields
   *   Custom fields to set default value.
   */
  private function populateDefaultFields(CRM_Core_Form &$form, array &$customFields) {
    $caseId = CRM_Utils_Request::retrieve('caseId', 'Positive', $form);
    if (!$caseId) {
      return;
    }

    $customFields['case']['value'] = $caseId;
    $caseClient = CaseContact::get()
      ->addSelect('contact_id')
      ->addWhere('case_id', '=', $caseId)
      ->execute()
      ->first()['contact_id'] ?? NULL;

    $form->setDefaults(array_merge($form->_defaultValues, ['contact_id' => $caseClient]));
  }

  /**
   * Checks if the hook should run.
   *
   * @param string $formName
   *   Form Name.
   *
   * @return bool
   *   True if hook should run, otherwise false.
   */
  public function shouldRun($formName) {
    return $formName === "CRM_Contribute_Form_Contribution";
  }

}
