<?php

/**
 * Adds sales order line items to the contribution.
 */
class CRM_Civicase_Hook_alterContent_AddSalesOrderLineToContribution {

  /**
   * Stores the sales order line items retrieved from the cache.
   *
   * @var array
   */
  private $salesOrderLineItems;

  /**
   * Constructs the AddSalesOrderLindeToContribution class.
   *
   * @param string $content
   *   The content to be altered.
   * @param array $context
   *   The context for the hook.
   * @param string $tplName
   *   The name of the template.
   */
  public function __construct(private &$content, private $context, private $tplName) {
    $this->salesOrderLineItems = \Civi::cache('short')->get('sales_order_line_items');
  }

  /**
   * Add sales order line items to the contribution.
   */
  public function run() {
    if (!$this->shouldRun()) {
      return;
    }

    $this->addLineItems();
  }

  /**
   * Adds sales order line items to the contribution.
   *
   * This method retrieves the sales order line items from the cache, and then
   * updates the corresponding input fields in the contribution
   * page's HTML content.
   */
  public function addLineItems() {
    $dom = new DomDocument();
    $dom->loadHTML($this->content);

    $table = $dom->getElementById('info');

    // Delete the first row (index 0)
    if ($table) {
      $firstRow = $table->getElementsByTagName('tr')->item(0);
      if ($firstRow) {
        $table->removeChild($firstRow);
      }
    }

    $rows = $table->getElementsByTagName('tr');
    // Set the values in the DOM.
    foreach ($this->salesOrderLineItems as $index => $item) {
      if ($index < $rows->length) {
        // Get the current row.
        $row = $rows->item($index);

        // Remove 'hiddenElement' class if it exists.
        $row->setAttribute('class', str_replace('hiddenElement', '', $row->getAttribute('class')));

        // Set the values from the line item array.
        $inputs = $row->getElementsByTagName('input');
        $selects = $row->getElementsByTagName('select');

        foreach ($inputs as $input) {
          $name = $input->getAttribute('name');

          if (strpos($name, 'qty') !== FALSE) {
            $input->setAttribute('value', $item['qty']);
          }
          elseif (strpos($name, 'tax_amount') !== FALSE) {
            $input->setAttribute('value', $item['tax_amount']);
          }
          elseif (strpos($name, 'line_total') !== FALSE) {
            $input->setAttribute('value', $item['line_total']);
          }
          elseif (strpos($name, 'unit_price') !== FALSE) {
            $input->setAttribute('value', $item['unit_price']);
          }
          elseif (strpos($name, 'label') !== FALSE) {
            $input->setAttribute('value', $item['label']);
          }
        }

        foreach ($selects as $select) {
          $name = $select->getAttribute('name');

          if (strpos($name, 'financial_type_id') !== FALSE) {
            foreach ($select->getElementsByTagName('option') as $option) {
              if ($option->getAttribute('value') == $item['financial_type_id']) {
                $option->setAttribute('selected', 'selected');
                break;
              }
            }
          }
        }
      }
    }

    \Civi::cache('short')->delete('sales_order_line_items');

    $this->content = $dom->saveHTML();
  }

  /**
   * Determines whether the hook should run.
   *
   * @return bool
   *   TRUE if the hook should run, FALSE otherwise.
   */
  public function shouldRun() {
    return $this->tplName === "CRM/Contribute/Page/Tab.tpl" && $this->context == "page" && !empty($this->salesOrderLineItems);
  }

}
