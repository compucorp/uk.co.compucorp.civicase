<?php

use Civi\Api4\Address;
use Civi\Api4\CaseSalesOrder;
use Civi\Api4\CaseSalesOrderLine;
use Civi\Api4\Contact;
use Civi\Api4\Setting;
use CRM_Civicase_WorkflowMessage_SalesOrderInvoice as SalesOrderInvoice;

/**
 * Renders sales order invoice.
 */
class CRM_Civicase_Service_CaseSalesOrderInvoice {

  /**
   * CaseSalesOrderInvoice constructor.
   *
   * @param \CRM_Civicase_WorkflowMessage_SalesOrderInvoice $template
   *   Workflow template.
   */
  public function __construct(private SalesOrderInvoice $template) {
  }

  /**
   * Renders the sales order invoice message template.
   *
   * @param int $id
   *   Sales Order ID.
   *
   * @return array
   *   Rendered message, consistent of 'subject', 'text', 'html'
   */
  public function render(int $id) {
    $caseSalesOrder = CaseSalesOrder::get()
      ->addWhere('id', '=', $id)
      ->addChain('items', CaseSalesOrderLine::get()
        ->addWhere('sales_order_id', '=', '$id')
        ->addSelect('*', 'product_id.name', 'financial_type_id.name')
      )
      ->addChain('computedRates', CaseSalesOrder::computeTotal()
        ->setLineItems('$items')
      )
      ->addChain('client', Contact::get()
        ->addWhere('id', '=', '$client_id'), 0
      )
      ->execute()
      ->first();

    if (!empty($caseSalesOrder['client_id'])) {
      $caseSalesOrder['clientAddress'] = Address::get()
        ->addSelect('*', 'country_id:label', 'state_province_id:label')
        ->addWhere('contact_id', '=', $caseSalesOrder['client_id'])
        ->execute()
        ->first();
      $caseSalesOrder['clientAddress']['country'] = $caseSalesOrder['clientAddress']['country_id:label'];
      $caseSalesOrder['clientAddress']['state'] = $caseSalesOrder['clientAddress']['state_province_id:label'];
    }

    $caseSalesOrder['taxRates'] = $caseSalesOrder['computedRates'][0]['taxRates'] ?? [];
    $caseSalesOrder['quotation_date'] = date('Y-m-d', strtotime($caseSalesOrder['quotation_date']));

    $domain = CRM_Core_BAO_Domain::getDomain();
    $organisation = Contact::get()
      ->addSelect('image_URL')
      ->addWhere('id', '=', $domain->contact_id)
      ->execute()
      ->first();

    $model = new CRM_Civicase_WorkflowMessage_SalesOrderInvoice();
    $terms = self::getTerms();
    $model->setDomainLogo($organisation['image_URL']);
    $model->setSalesOrder($caseSalesOrder);
    $model->setTerms($terms);
    $model->setSalesOrderId($id);
    $model->setDomainLocation(self::getDomainLocation());
    $model->setDomainName($domain->name ?? '');
    $rendered = $model->renderTemplate();

    $rendered['format'] = $rendered['format'] ?? self::defaultInvoiceFormat();

    return $rendered;
  }

  /**
   * Returns the Quotation invoice terms.
   */
  private static function getTerms() {
    $terms = NULL;
    $invoicing = Setting::get()
      ->addSelect('invoicing')
      ->execute()
      ->first();

    if (!empty($invoicing['value'])) {
      $terms = Civi::settings()->get('quotations_notes');
    }

    return $terms;
  }

  /**
   * Gets domain location.
   *
   * @return array
   *   An array of address lines.
   */
  private static function getDomainLocation() {
    $domain = CRM_Core_BAO_Domain::getDomain();
    $locParams = ['contact_id' => $domain->contact_id];
    $locationDefaults = CRM_Core_BAO_Location::getValues($locParams);
    if (empty($locationDefaults['address'][1])) {
      return [];
    }
    $stateProvinceId = $locationDefaults['address'][1]['state_province_id'] ?? NULL;
    $stateProvinceAbbreviationDomain = !empty($stateProvinceId) ? CRM_Core_PseudoConstant::stateProvinceAbbreviation($stateProvinceId) : '';
    $countryId = $locationDefaults['address'][1]['country_id'];
    $countryDomain = !empty($countryId) ? CRM_Core_PseudoConstant::country($countryId) : '';

    return [
      'street_address' => CRM_Utils_Array::value('street_address', CRM_Utils_Array::value('1', $locationDefaults['address'])),
      'supplemental_address_1' => CRM_Utils_Array::value('supplemental_address_1', CRM_Utils_Array::value('1', $locationDefaults['address'])),
      'supplemental_address_2' => CRM_Utils_Array::value('supplemental_address_2', CRM_Utils_Array::value('1', $locationDefaults['address'])),
      'supplemental_address_3' => CRM_Utils_Array::value('supplemental_address_3', CRM_Utils_Array::value('1', $locationDefaults['address'])),
      'city' => CRM_Utils_Array::value('city', CRM_Utils_Array::value('1', $locationDefaults['address'])),
      'postal_code' => CRM_Utils_Array::value('postal_code', CRM_Utils_Array::value('1', $locationDefaults['address'])),
      'state' => $stateProvinceAbbreviationDomain,
      'country' => $countryDomain,
    ];
  }

  /**
   * Returns the default format to use for Invoice.
   */
  private static function defaultInvoiceFormat() {
    return [
      'margin_top' => 10,
      'margin_left' => 65,
      'metric' => 'px',
    ];
  }

}
