<?php

use Civi\WorkflowMessage\GenericWorkflowMessage;

/**
 * Invoice for case sales order(Quotations).
 *
 * @support template-only
 *
 * @method ?string getTerms()
 * @method $this setTerms(?string $terms)
 * @method ?array getSalesOrder()
 * @method $this setSalesOrder(?array $salesOrder)
 * @method ?array getDomainLocation()
 * @method $this setDomainLocation(?array $domainLocation)
 * @method ?string getDomainLogo()
 * @method $this setDomainLogo(?string $logo)
 */
class CRM_Civicase_WorkflowMessage_SalesOrderInvoice extends GenericWorkflowMessage {

  public const WORKFLOW = 'sales_order_invoice';

  /**
   * Quotation Terms.
   *
   * @var string
   * @scope tplParams
   */
  public $terms;

  /**
   * Sales Order object.
   *
   * @var array
   * @scope tplParams as sales_order
   */
  protected $salesOrder;

  /**
   * Domain location information.
   *
   * @var array
   * @scope tplParams as domain_location
   */
  protected $domainLocation;

  /**
   * Domain Orgnanisation Image URL.
   *
   * @var array
   * @scope tplParams as domain_logo
   */
  protected $domainLogo;

}
