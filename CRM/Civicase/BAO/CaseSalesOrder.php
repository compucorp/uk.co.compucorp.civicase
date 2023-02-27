<?php

/**
 * CaseSalesOrder BAO.
 */
class CRM_Civicase_BAO_CaseSalesOrder extends CRM_Civicase_DAO_CaseSalesOrder {

  /**
   * Create a new CaseSalesOrder based on array-data.
   *
   * @param array $params
   *   Key-value pairs.
   *
   * @return CRM_Civicase_DAO_CaseSalesOrder|null
   *   Case sales order instance.
   */
  public static function create(array $params) {
    $className = 'CRM_Civicase_DAO_CaseSalesOrder';
    $entityName = 'CaseSalesOrder';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

  /**
   * Computes the sales order line item total.
   *
   * @param array $items
   *   Array of sales order line items.
   *
   * @return array
   *   ['totalAfterTax' => <value>, 'totalBeforeTax' => <value>]
   */
  public static function computeTotal(array $items) {
    $totalBeforeTax = round(array_reduce($items, fn ($a, $b) => $a + self::getSubTotal($b), 0), 2);
    $totalAfterTax = round(array_reduce($items,
      fn ($a, $b) => $a + (($b['tax_rate'] * self::getSubTotal($b)) / 100),
      0
    ) + $totalBeforeTax, 2);

    return [
      'taxRates' => self::computeTaxRates($items),
      'totalAfterTax' => $totalAfterTax,
      'totalBeforeTax' => $totalBeforeTax,
    ];
  }

  /**
   * Computes the sub total of a single line item.
   *
   * @param array $item
   *   Single sales order line item.
   *
   * @return int
   *   The line item subtotal.
   */
  public static function getSubTotal(array $item) {
    return $item['unit_price'] * $item['quantity'] * ((100 - ($item['discounted_percentage'] ?? 0)) / 100) ?? 0;
  }

  /**
   * Computes the tax rates of each line item.
   *
   * @param array $items
   *   Single sales order line item.
   *
   * @return array
   *   Returned sorted array of line items tax rates.
   */
  public static function computeTaxRates(array $items) {
    $items = array_filter($items, fn ($a) => $a['tax_rate'] > 0);
    usort($items, fn ($a, $b) => $a['tax_rate'] <=> $b['tax_rate']);

    return array_map(
      fn ($a) =>
      [
        'rate' => round($a['tax_rate'], 2),
        'value' => round(($a['tax_rate'] * self::getSubTotal($a)) / 100, 2),
      ],
      $items
    );
  }

}
