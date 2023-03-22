<?php

use Civi\Api4\Address;
use Civi\Api4\CaseSalesOrder;
use Civi\Api4\Contact;
use Civi\Api4\Email;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * CaseSalesOrder Invoice Email Test Case.
 *
 * @group headless
 */
class CRM_Civicase_Form_CaseSalesOrderInvoiceTest extends BaseHeadlessTest {
  use Helpers_CaseSalesOrderTrait;
  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Setup data before tests run.
   */
  public function setUp() {
    CRM_Core_Invoke::rebuildMenuAndCaches(TRUE);
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
  }

  /**
   * Ensures user sees the email form when the Email URL is accessed.
   */
  public function testEmailFormWillDisplayAsExpected() {
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder = (object) (CaseSalesOrder::save(FALSE)
      ->addRecord($salesOrder)
      ->execute()
      ->first());

    Email::save(FALSE)
      ->addRecord([
        "contact_id" => $salesOrder->client_id,
        "location_type_id" => 2,
        "email" => "junwe@mail.com",
        "is_primary" => TRUE,
        "on_hold" => 0,
      ])
      ->execute();

    $link = "civicrm/case-features/quotations/email?id={$salesOrder->id}";
    $page = $this->imitateLinkVisit($link);

    $this->assertRegExp('/name="subject"/', $page);
    $this->assertRegExp('/name="from_email_address"/', $page);
  }

  /**
   * Ensures the To Email is set to client email on email form.
   */
  public function testToEmailIsSetByDefaulToSalesOrderClientEmail() {
    $expectedToEmail = "junwe@mail.com";
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder = (object) (CaseSalesOrder::save(FALSE)
      ->addRecord($salesOrder)
      ->execute()
      ->first());

    Email::save(FALSE)
      ->addRecord([
        "contact_id" => $salesOrder->client_id,
        "location_type_id" => 2,
        "email" => $expectedToEmail,
        "is_primary" => TRUE,
        "on_hold" => 0,
      ])
      ->execute();

    $link = "civicrm/case-features/quotations/email?id={$salesOrder->id}";
    $page = $this->imitateLinkVisit($link);

    $this->assertRegExp('/<' . $expectedToEmail . '>/', $page);
  }

  /**
   * Ensures invoice will render expected tokens & tplParams.
   *
   * We only cheeck for some fields, that is enough to show that
   * the right case sales order entity value is passed to
   * the invoice.
   */
  public function testInvoiceRendersAsExpected() {
    $salesOrder = $this->getCaseSalesOrderData();
    $salesOrder['items'][] = $lineItem1 = $this->getCaseSalesOrderLineData();
    $salesOrder['items'][] = $lineItem2 = $this->getCaseSalesOrderLineData();

    $salesOrder = (object) (CaseSalesOrder::save(FALSE)
      ->addRecord($salesOrder)
      ->execute()
      ->first());

    $address = Address::save(FALSE)
      ->addRecord([
        "contact_id" => $salesOrder->client_id,
        "location_type_id" => 5,
        "is_primary" => TRUE,
        "is_billing" => TRUE,
        "street_address" => "Coldharbour Ln",
        "street_number" => "42",
        "supplemental_address_1" => "Supplementary Address 1",
        "supplemental_address_2" => "Supplementary Address 2",
        "supplemental_address_3" => "Supplementary Address 3",
        "city" => "Hayes",
        "postal_code" => "UB3 3EA",
        "country_id" => 1226,
        "manual_geo_code" => FALSE,
        "timezone" => NULL,
        "name" => NULL,
        "master_id" => NULL,
      ])
      ->execute()
      ->first();

    $contact = (object) (Contact::get(FALSE)
      ->addWhere('id', '=', $salesOrder->client_id)
      ->execute()
      ->first());

    $_REQUEST['id'] = $_GET['id'] = $salesOrder->id;

    $invoice = CRM_Civicase_Form_CaseSalesOrderInvoice::getQuotationInvoice();

    $totalBeforeTax = CRM_Utils_Money::format($salesOrder->total_before_tax, $salesOrder->currency);
    $totalAfterTax = CRM_Utils_Money::format($salesOrder->total_after_tax, $salesOrder->currency);
    $this->assertArrayHasKey("html", $invoice);
    $this->assertRegExp('/' . $contact->display_name . '/', $invoice['html']);
    $this->assertRegExp('/Supplementary Address 1/', $invoice['html']);
    $this->assertRegExp('/Supplementary Address 2/', $invoice['html']);
    $this->assertRegExp('/' . $salesOrder->description . '/', $invoice['html']);
    $this->assertRegExp('/' . str_replace(' ', '', $totalBeforeTax) . '/', $invoice['html']);
    $this->assertRegExp('/' . str_replace(' ', '', $totalAfterTax) . '/', $invoice['html']);
    $this->assertRegExp('/' . $lineItem1['item_description'] . '/', $invoice['html']);
    $this->assertRegExp('/' . $lineItem2['item_description'] . '/', $invoice['html']);
    $this->assertRegExp('/' . $lineItem1['quantity'] . '/', $invoice['html']);
    $this->assertRegExp('/' . $lineItem2['quantity'] . '/', $invoice['html']);
  }

  /**
   * Visits a CiviCRM link and returns the page content.
   *
   * @param string $url
   *   URL to the page.
   *
   * @return string
   *   Content of the page.
   */
  public function imitateLinkVisit(string $url) {
    $_SERVER['REQUEST_URI'] = $url;
    $urlParts = explode('?', $url);
    $_GET['q'] = $urlParts[0];

    if (!empty($urlParts[1])) {
      $parsed = [];
      parse_str($urlParts[1], $parsed);
      foreach ($parsed as $param => $value) {
        $_REQUEST[$param] = $value;
      }
    }

    $item = CRM_Core_Invoke::getItem([$_GET['q']]);
    ob_start();
    CRM_Core_Invoke::runItem($item);
    return ob_get_clean();
  }

}
