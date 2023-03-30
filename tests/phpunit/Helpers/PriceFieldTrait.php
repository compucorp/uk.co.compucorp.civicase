<?php

/**
 * Case setting helper trait.
 */
trait Helpers_PriceFieldTrait {

  /**
   * Generates price fields for testing.
   *
   * @see CRM_Lineitemedit_Util
   */
  public function generatePriceField() {
    $priceField = civicrm_api3('PriceField',
      'getsingle',
      [
        'price_set_id' => civicrm_api3('PriceSet', 'getvalue', [
          'name' => 'default_contribution_amount',
          'return' => 'id',
        ]),
        'options' => ['limit' => 1],
      ]
    );
    $priceFieldParams = $priceField;
    unset(
      $priceFieldParams['id'],
      $priceFieldParams['name'],
      $priceFieldParams['weight'],
      $priceFieldParams['is_required']
    );
    $priceFieldValue = civicrm_api3('PriceFieldValue',
      'getsingle',
      [
        'price_field_id' => $priceField['id'],
        'options' => ['limit' => 1],
      ]
    );
    $priceFieldValueParams = $priceFieldValue;
    unset($priceFieldValueParams['id'], $priceFieldValueParams['name'], $priceFieldValueParams['weight']);
    for ($i = 1; $i <= 30; ++$i) {
      $params = array_merge($priceFieldParams, ['label' => ts('Additional Line Item') . " $i"]);
      $priceField = civicrm_api3('PriceField', 'get', $params)['values'];
      if (empty($priceField)) {
        $p = civicrm_api3('PriceField', 'create', $params);
        civicrm_api3('PriceFieldValue', 'create', array_merge(
          $priceFieldValueParams,
          [
            'label' => ts('Additional Item') . " $i",
            'price_field_id' => $p['id'],
          ]
        ));
      }
      else {
        civicrm_api3('PriceField', 'create', [
          'id' => key($priceField),
          'is_active' => TRUE,
        ]);
      }
    }
  }

}
