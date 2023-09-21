<?php

use Civi\Api4\CaseSalesOrder;
use Civi\Token\TokenProcessor;

/**
 * Handles Sales Order Email Invoice Task.
 */
class CRM_Civicase_Form_CaseSalesOrderInvoice extends CRM_Core_Form {
  use CRM_Contact_Form_Task_EmailTrait;

  /**
   * The array that holds all the contact ids.
   *
   * @var array
   */
  public $_contactIds; // phpcs:ignore

  /**
   * Current form context.
   *
   * @var string
   */
  public $_context; // phpcs:ignore

  /**
   * Sales Order ID.
   *
   * @var int
   */
  public $salesOrderId;

  /**
   * {@inheritDoc}
   */
  public function preProcess() {
    $this->setTitle('Email Quotation');
    $this->salesOrderId = CRM_Utils_Request::retrieveValue('id', 'Positive');

    $this->setContactIDs();
    $this->setIsSearchContext(FALSE);
    $this->traitPreProcess();
  }

  /**
   * List available tokens for this form.
   *
   * Presently all tokens are returned.
   *
   * @return array
   *   List of Available tokens
   *
   * @throws \CRM_Core_Exception
   */
  public function listTokens() {
    $tokenProcessor = new TokenProcessor(Civi::dispatcher(), ['schema' => ['contactId']]);
    $tokens = $tokenProcessor->listTokens();

    return $tokens;
  }

  /**
   * Submit the form values.
   *
   * This is also accessible for testing.
   *
   * @param array $formValues
   *   Submitted values.
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   * @throws \API_Exception
   */
  public function submit(array $formValues): void {
    $this->saveMessageTemplate($formValues);
    $sents = 0;
    $from = $formValues['from_email_address'];
    $text = $this->getSubmittedValue('text_message');
    $html = $this->getSubmittedValue('html_message');
    $from = CRM_Utils_Mail::formatFromAddress($from);

    $cc = $this->getCc();
    $additionalDetails = empty($cc) ? '' : "\ncc : " . $this->getEmailUrlString($this->getCcArray());

    $bcc = $this->getBcc();
    $additionalDetails .= empty($bcc) ? '' : "\nbcc : " . $this->getEmailUrlString($this->getBccArray());

    $quotationInvoice = self::getQuotationInvoice();

    foreach ($this->getRowsForEmails() as $values) {
      $mailParams = [];
      $mailParams['messageTemplate'] = [
        'msg_text' => $text,
        'msg_html' => $html,
        'msg_subject' => $this->getSubject(),
      ];
      $mailParams['tokenContext'] = [
        'contactId' => $values['contact_id'],
        'salesOrderId' => $this->salesOrderId,
      ];
      $mailParams['tplParams'] = [];
      $mailParams['from'] = $from;
      $mailParams['toEmail'] = $values['email'];
      $mailParams['cc'] = $cc ?? NULL;
      $mailParams['bcc'] = $bcc ?? NULL;
      $mailParams['attachments'][] = CRM_Utils_Mail::appendPDF('quotation_invoice.pdf', $quotationInvoice['html'], $quotationInvoice['format']);
      // Send the mail.
      [$sent, $subject, $message, $html] = CRM_Core_BAO_MessageTemplate::sendTemplate($mailParams);
      $sents += ($sent ? 1 : 0);
    }

    CRM_Core_Session::setStatus(ts('One email has been sent successfully. ', [
      'plural' => '%count emails were sent successfully. ',
      'count' => $sents,
    ]), ts('Message Sent', ['plural' => 'Messages Sent', 'count' => $sents]), 'success');

  }

  /**
   * {@inheritDoc}
   */
  public function setContactIDs() { // phpcs:ignore
    $this->_contactIds = $this->getContactIds();
  }

  /**
   * Returns Sales Order Client Contact ID.
   *
   * @return array
   *   Client Contact ID as an array
   */
  protected function getContactIds(): array {
    if (isset($this->_contactIds)) {
      return $this->_contactIds;
    }

    $salesOrderId = CRM_Utils_Request::retrieveValue('id', 'Positive');

    $caseSalesOrder = CaseSalesOrder::get()
      ->addWhere('id', '=', $salesOrderId)
      ->execute()
      ->first();

    $this->_contactIds = [$caseSalesOrder['client_id']];

    return $this->_contactIds;
  }

  /**
   * Renders the quotatioin invoice message template.
   *
   * @return array
   *   Rendered message, consistent of 'subject', 'text', 'html'
   */
  public static function getQuotationInvoice(): array {
    $salesOrderId = CRM_Utils_Request::retrieveValue('id', 'Positive');

    /** @var \CRM_Civicase_Service_CaseSalesOrderInvoice */
    $invoiceService = new \CRM_Civicase_Service_CaseSalesOrderInvoice(new \CRM_Civicase_WorkflowMessage_SalesOrderInvoice());
    return $invoiceService->render($salesOrderId);
  }

  /**
   * Get the rows for each contactID.
   *
   * @return array
   *   Array if contact IDs.
   */
  protected function getRows(): array {
    $rows = [];
    foreach ($this->_contactIds as $index => $contactID) {
      $rows[] = [
        'contact_id' => $contactID,
        'schema' => ['contactId' => $contactID],
      ];
    }
    return $rows;
  }

  /**
   * Renders and return the generated PDF to the browser.
   */
  public static function download(): void {
    $rendered = self::getQuotationInvoice();
    ob_end_clean();
    CRM_Utils_PDF_Utils::html2pdf($rendered['html'], 'quotation_invoice.pdf', FALSE, $rendered['format']);
    CRM_Utils_System::civiExit();
  }

}
