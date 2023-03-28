<?php

use Civi\Api4\MessageTemplate;
use Civi\Api4\OptionValue;
use CRM_Civicase_ExtensionUtil as E;
use CRM_Civicase_WorkflowMessage_SalesOrderInvoice as SalesOrderInvoice;

/**
 * Manages Quotation Invoice Template.
 */
class CRM_Civicase_Setup_Manage_QuotationTemplateManager extends CRM_Civicase_Setup_Manage_AbstractManager {

  /**
   * Adds custom quotation invoice template.
   */
  public function create(): void {
    $messageTemplate = MessageTemplate::get(FALSE)
      ->addSelect('id')
      ->addWhere('workflow_name', '=', SalesOrderInvoice::WORKFLOW)
      ->execute()
      ->first();

    $templatePath = E::path('/templates/CRM/Civicase/MessageTemplate/QuotationInvoice.tpl');
    $templateBodyHtml = file_get_contents($templatePath);

    $params = [
      'workflow_name' => SalesOrderInvoice::WORKFLOW,
      'msg_title' => 'Quotation Invoice',
      'msg_subject' => 'Quotation Invoice',
      'msg_html' => $templateBodyHtml,
      'is_reserved' => 0,
      'is_default' => 1,
    ];

    if (!empty($messageTemplate)) {
      $params = array_merge(['id' => $messageTemplate['id']], $params);
    }

    $optionValue = OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'msg_tpl_workflow_case')
      ->addWhere('name', '=', SalesOrderInvoice::WORKFLOW)
      ->execute()
      ->first();

    if (empty($optionValue)) {
      $optionValue = OptionValue::create(FALSE)
        ->addValue('option_group_id.name', 'msg_tpl_workflow_case')
        ->addValue('label', 'Quotation Invoice')
        ->addValue('name', SalesOrderInvoice::WORKFLOW)
        ->execute()
        ->first();
    }

    $params['workflow_id'] = $optionValue['id'];

    MessageTemplate::save(FALSE)->addRecord($params)->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function remove(): void {
    MessageTemplate::delete(FALSE)
      ->addWhere('workflow_name', '=', SalesOrderInvoice::WORKFLOW)
      ->execute();

    OptionValue::delete(FALSE)
      ->addWhere('option_group_id:name', '=', 'msg_tpl_workflow_case')
      ->addWhere('name', '=', SalesOrderInvoice::WORKFLOW)
      ->execute()
      ->first();
  }

  /**
   * {@inheritDoc}
   */
  protected function toggle($status): void {
    MessageTemplate::update(FALSE)
      ->addValue('is_active', $status)
      ->addWhere('workflow_name', '=', SalesOrderInvoice::WORKFLOW)
      ->execute();
  }

}
