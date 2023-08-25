<?php

/**
 * @file
 * Exported Product Discounts CustomGroup.
 */

use Civi\Api4\OptionValue;

$mgd = [
  [
    'name' => 'CustomGroup_Product_Discounts',
    'entity' => 'CustomGroup',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Product_Discounts',
        'title' => 'Product Discounts',
        'extends' => 'MembershipType',
        'extends_entity_column_value' => NULL,
        'style' => 'Inline',
        'collapse_display' => FALSE,
        'help_pre' => '',
        'help_post' => '',
        'weight' => 107,
        'is_active' => TRUE,
        'is_multiple' => FALSE,
        'min_multiple' => NULL,
        'max_multiple' => NULL,
        'collapse_adv_display' => TRUE,
        'created_date' => '2023-08-25 07:22:08',
        'is_reserved' => FALSE,
        'is_public' => TRUE,
        'icon' => '',
        'extends_entity_column_id' => NULL,
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Product_Discounts_CustomField_Product_Discount_Amount',
    'entity' => 'CustomField',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Product_Discounts',
        'name' => 'Product_Discount_Amount',
        'label' => 'Product Discount Amount',
        'data_type' => 'Float',
        'html_type' => 'Text',
        'default_value' => NULL,
        'is_required' => FALSE,
        'is_searchable' => FALSE,
        'is_search_range' => FALSE,
        'help_pre' => NULL,
        'help_post' => 'Specify a discount that will automatically be applied when adding a product line item to a quotation if the contact is a member of this type.',
        'mask' => NULL,
        'attributes' => NULL,
        'javascript' => NULL,
        'is_active' => TRUE,
        'is_view' => FALSE,
        'options_per_line' => NULL,
        'text_length' => 255,
        'start_date_years' => NULL,
        'end_date_years' => NULL,
        'date_format' => NULL,
        'time_format' => NULL,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'product_discount_amount',
        'serialize' => 0,
        'filter' => NULL,
        'in_selector' => FALSE,
      ],
    ],
  ],
];

$rowCount = OptionValue::get(FALSE)
  ->selectRowCount()
  ->addSelect('*')
  ->addWhere('option_group_id:name', '=', 'cg_extend_objects')
  ->addWhere('name', '=', 'civicrm_membership_type')
  ->execute()
  ->count();

if ($rowCount == 1) {
  return $mgd;
}

return [];
