<?php

/**
 * @file
 */

use CRM_Civicase_ExtensionUtil as E;

/**
 * @file
 * Exported quotations saved search.
 */

$mgd = [
    [
      'name' => 'SavedSearch_Civicase_Quotations',
      'entity' => 'SavedSearch',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'Civicase_Quotations',
          'label' => E::ts('Civicase Quotations'),
          'api_entity' => 'CaseSalesOrder',
          'api_params' => [
            'version' => 4,
            'select' => [
              'id',
              'client_id.display_name',
              'quotation_date AS quotation_date',
              'description',
              'owner_id.display_name',
              'CONCAT_WS(" ", total_before_tax, currency:label) AS CONCAT_WS_total_before_tax_currency_label',
              'CONCAT_WS(" ", total_after_tax, currency:label) AS CONCAT_WS_total_after_tax_currency_label',
              'status_id:label',
              'invoicing_status_id:label',
              'payment_status_id:label',
              'CaseSalesOrder_Case_case_id_01.start_date',
            ],
            'orderBy' => [
              'created_at' => 'DESC',
            ],
            'where' => [],
            'groupBy' => [],
            'join' => [
              [
                'Case AS CaseSalesOrder_Case_case_id_01',
                'LEFT',
                [
                  'case_id',
                  '=',
                  'CaseSalesOrder_Case_case_id_01.id',
                ],
              ],
            ],
            'having' => [],
            'limit' => 10,
          ],
        ],
        'match' => [
          'name',
        ],
      ],
    ],
    [
      'name' => 'SavedSearch_Civicase_Quotations_SearchDisplay_Civicase_Contact_Quotations_Table',
      'entity' => 'SearchDisplay',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'Civicase_Contact_Quotations_Table',
          'label' => E::ts('Contact Quotations List'),
          'saved_search_id.name' => 'Civicase_Quotations',
          'type' => 'table',
          'settings' => [
            'actions' => TRUE,
            'limit' => 10,
            'classes' => [
              'table',
              'table-striped',
            ],
            'pager' => [],
            'placeholder' => 5,
            'sort' => [
              [
                'created_at',
                'DESC',
              ],
            ],
            'columns' => [
              [
                'type' => 'field',
                'key' => 'id',
                'dataType' => 'Integer',
                'label' => E::ts('Quote No.'),
                'sortable' => TRUE,
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'quotation_date',
                'dataType' => 'Date',
                'label' => E::ts('Date'),
                'sortable' => TRUE,
                'alignment' => '',
                'rewrite' => '{capture assign=my_date}[quotation_date]{/capture}
                {capture assign="day"}{$my_date|date_format:"%e"|trim}{/capture}
                {capture assign="suffix"}
                    {if $day == 1 || $day == 21 || $day == 31}st
                    {elseif $day == 2 || $day == 22}nd
                    {elseif $day == 3 || $day == 23}rd
                    {else}th
                    {/if}
                {/capture}
                {$day|trim}{$suffix|trim} {$my_date|date_format:"%B %Y"}',
              ],
              [
                'type' => 'field',
                'key' => 'owner_id.display_name',
                'dataType' => 'String',
                'label' => E::ts('Owner'),
                'sortable' => TRUE,
                'link' => [
                  'path' => '',
                  'entity' => 'Contact',
                  'action' => 'view',
                  'join' => 'owner_id',
                  'target' => '_blank',
                ],
                'title' => E::ts('View Owner'),
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'CONCAT_WS_total_before_tax_currency_label',
                'dataType' => 'String',
                'label' => E::ts('Total Before Tax'),
                'sortable' => TRUE,
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'CONCAT_WS_total_after_tax_currency_label',
                'dataType' => 'String',
                'label' => E::ts('Total After Tax'),
                'sortable' => TRUE,
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'invoicing_status_id:label',
                'dataType' => 'Integer',
                'label' => E::ts('Invoicing'),
                'sortable' => TRUE,
              ],
              [
                'type' => 'field',
                'key' => 'payment_status_id:label',
                'dataType' => 'Integer',
                'label' => E::ts('Payments'),
                'sortable' => TRUE,
              ],
              [
                'type' => 'field',
                'key' => 'status_id:label',
                'dataType' => 'Integer',
                'label' => E::ts('Status'),
                'sortable' => TRUE,
                'alignment' => '',
              ],
              [
                'text' => '',
                'style' => 'default',
                'size' => 'btn-sm',
                'icon' => 'fa-bars',
                'links' => [
                  [
                    'path' => '',
                    'icon' => 'fa-external-link',
                    'text' => E::ts('View'),
                    'style' => 'default',
                    'condition' => [],
                    'entity' => 'CaseSalesOrder',
                    'action' => 'view',
                    'join' => '',
                    'target' => '_blank',
                    'task' => '',
                  ],
                  [
                    'entity' => 'CaseSalesOrder',
                    'action' => 'update',
                    'join' => '',
                    'target' => '_blank',
                    'icon' => 'fa-pencil',
                    'text' => E::ts('Edit'),
                    'style' => 'default',
                    'path' => '',
                    'condition' => [],
                    'task' => '',
                  ],
                  [
                    'entity' => 'CaseSalesOrder',
                    'action' => 'delete',
                    'join' => '',
                    'target' => 'crm-popup',
                    'icon' => 'fa-trash',
                    'text' => E::ts('Delete'),
                    'style' => 'default',
                    'path' => '',
                    'condition' => [],
                    'task' => '',
                  ],
                  [
                    'path' => 'civicrm/case-features/quotations/download-pdf?id=[id]',
                    'icon' => 'fa-file-pdf-o',
                    'text' => E::ts('Download PDF'),
                    'style' => 'default',
                    'condition' => [],
                    'entity' => '',
                    'action' => '',
                    'join' => '',
                    'target' => '_blank',
                    'task' => '',
                  ],
                  [
                    'path' => 'civicrm/case-features/quotations/create-contribution?id=[id]',
                    'icon' => 'fa-credit-card',
                    'text' => E::ts('Create Contribution'),
                    'style' => 'default',
                    'condition' => [],
                    'entity' => '',
                    'action' => '',
                    'join' => '',
                    'target' => 'crm-popup',
                    'task' => '',
                  ],
                  [
                    'path' => 'civicrm/case-features/quotations/email?id=[id]',
                    'icon' => 'fa-paper-plane-o',
                    'text' => E::ts('Send By Email'),
                    'style' => 'default',
                    'condition' => [],
                    'entity' => '',
                    'action' => '',
                    'join' => '',
                    'target' => 'crm-popup',
                    'task' => '',
                  ],
                ],
                'type' => 'menu',
                'alignment' => '',
                'label' => E::ts('Actions'),
              ],
            ],
            'noResultsText' => 'No quotations found',
          ],
        ],
        'match' => [
          'saved_search_id',
          'name',
        ],
      ],
    ],
    [
      'name' => 'SavedSearch_Civicase_Quotations_SearchDisplay_Civicase_Quotations_Table',
      'entity' => 'SearchDisplay',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'Civicase_Quotations_Table',
          'label' => E::ts('Quotations List'),
          'saved_search_id.name' => 'Civicase_Quotations',
          'type' => 'table',
          'settings' => [
            'actions' => TRUE,
            'limit' => 10,
            'classes' => [
              'table',
              'table-striped',
            ],
            'pager' => [],
            'placeholder' => 5,
            'sort' => [
              [
                'created_at',
                'DESC',
              ],
            ],
            'columns' => [
              [
                'type' => 'field',
                'key' => 'id',
                'dataType' => 'Integer',
                'label' => E::ts('Quote No.'),
                'sortable' => TRUE,
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'client_id.display_name',
                'dataType' => 'String',
                'label' => E::ts('Client'),
                'sortable' => TRUE,
                'link' => [
                  'path' => '',
                  'entity' => 'Contact',
                  'action' => 'view',
                  'join' => 'client_id',
                  'target' => '_blank',
                ],
                'title' => E::ts('View Client'),
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'quotation_date',
                'dataType' => 'Date',
                'label' => E::ts('Date'),
                'sortable' => TRUE,
                'alignment' => '',
                'rewrite' => '{capture assign=my_date}[quotation_date]{/capture}
                {capture assign="day"}{$my_date|date_format:"%e"|trim}{/capture}
                {capture assign="suffix"}
                    {if $day == 1 || $day == 21 || $day == 31}st
                    {elseif $day == 2 || $day == 22}nd
                    {elseif $day == 3 || $day == 23}rd
                    {else}th
                    {/if}
                {/capture}
                {$day|trim}{$suffix|trim} {$my_date|date_format:"%B %Y"}',
              ],
              [
                'type' => 'field',
                'key' => 'owner_id.display_name',
                'dataType' => 'String',
                'label' => E::ts('Owner'),
                'sortable' => TRUE,
                'link' => [
                  'path' => '',
                  'entity' => 'Contact',
                  'action' => 'view',
                  'join' => 'owner_id',
                  'target' => '_blank',
                ],
                'title' => E::ts('View Owner'),
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'CaseSalesOrder_Case_case_id_01.start_date',
                'dataType' => 'Date',
                'label' => E::ts('Application Date'),
                'sortable' => TRUE,
              ],
              [
                'type' => 'field',
                'key' => 'CONCAT_WS_total_before_tax_currency_label',
                'dataType' => 'String',
                'label' => E::ts('Total Before Tax'),
                'sortable' => TRUE,
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'CONCAT_WS_total_after_tax_currency_label',
                'dataType' => 'String',
                'label' => E::ts('Total After Tax'),
                'sortable' => TRUE,
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'status_id:label',
                'dataType' => 'Integer',
                'label' => E::ts('Status'),
                'sortable' => TRUE,
                'alignment' => '',
              ],
              [
                'type' => 'field',
                'key' => 'invoicing_status_id:label',
                'dataType' => 'Integer',
                'label' => E::ts('Invoicing'),
                'sortable' => TRUE,
              ],
              [
                'type' => 'field',
                'key' => 'payment_status_id:label',
                'dataType' => 'Integer',
                'label' => E::ts('Payments'),
                'sortable' => TRUE,
              ],
              [
                'text' => '',
                'style' => 'default',
                'size' => 'btn-sm',
                'icon' => 'fa-bars',
                'links' => [
                  [
                    'path' => '',
                    'icon' => 'fa-external-link',
                    'text' => E::ts('View'),
                    'style' => 'default',
                    'condition' => [],
                    'entity' => 'CaseSalesOrder',
                    'action' => 'view',
                    'join' => '',
                    'target' => '_blank',
                    'task' => '',
                  ],
                  [
                    'entity' => 'CaseSalesOrder',
                    'action' => 'update',
                    'join' => '',
                    'target' => '_blank',
                    'icon' => 'fa-pencil',
                    'text' => E::ts('Edit'),
                    'style' => 'default',
                    'path' => '',
                    'condition' => [],
                    'task' => '',
                  ],
                  [
                    'entity' => 'CaseSalesOrder',
                    'action' => 'delete',
                    'join' => '',
                    'target' => 'crm-popup',
                    'icon' => 'fa-trash',
                    'text' => E::ts('Delete'),
                    'style' => 'default',
                    'path' => '',
                    'condition' => [],
                    'task' => '',
                  ],
                  [
                    'path' => 'civicrm/case-features/quotations/download-pdf?id=[id]',
                    'icon' => 'fa-file-pdf-o',
                    'text' => E::ts('Download PDF'),
                    'style' => 'default',
                    'condition' => [],
                    'entity' => '',
                    'action' => '',
                    'join' => '',
                    'target' => '_blank',
                    'task' => '',
                  ],
                  [
                    'path' => 'civicrm/case-features/quotations/create-contribution?id=[id]',
                    'icon' => 'fa-credit-card',
                    'text' => E::ts('Create Contribution'),
                    'style' => 'default',
                    'condition' => [],
                    'entity' => '',
                    'action' => '',
                    'join' => '',
                    'target' => 'crm-popup',
                    'task' => '',
                  ],
                  [
                    'path' => 'civicrm/case-features/quotations/email?id=[id]',
                    'icon' => 'fa-paper-plane-o',
                    'text' => E::ts('Send By Email'),
                    'style' => 'default',
                    'condition' => [],
                    'entity' => '',
                    'action' => '',
                    'join' => '',
                    'target' => 'crm-popup',
                    'task' => '',
                  ],
                ],
                'type' => 'menu',
                'alignment' => '',
                'label' => E::ts('Actions'),
              ],
            ],
            'noResultsText' => 'No quotations found',
          ],
        ],
        'match' => [
          'saved_search_id',
          'name',
        ],
      ],
    ],
    [
      'name' => 'SavedSearch_Quotation_Contributions',
      'entity' => 'SavedSearch',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'Quotation_Contributions',
          'label' => 'Quotation Contributions',
          'form_values' => NULL,
          'search_custom_id' => NULL,
          'api_entity' => 'Contribution',
          'api_params' => [
            'version' => 4,
            'select' => [
              'total_amount',
              'financial_type_id:label',
              'source',
              'receive_date',
              'thankyou_date',
              'contribution_status_id:label',
              'Opportunity_Details.Case_Opportunity',
            ],
            'orderBy' => [],
            'where' => [
              [
                'id',
                'IS NOT EMPTY',
              ],
              [
                'OR',
                [
                  [
                    'Opportunity_Details.Case_Opportunity',
                    'IS NOT EMPTY',
                  ],
                  [
                    'Opportunity_Details.Quotation',
                    'IS NOT EMPTY',
                  ],
                ],
              ],
            ],
            'groupBy' => [],
            'join' => [],
            'having' => [],
          ],
          'expires_date' => NULL,
          'description' => NULL,
          'mapping_id' => NULL,
        ],
      ],
    ],
    [
      'name' => 'SavedSearch_Quotation_Contributions_SearchDisplay_Contributions_Table_1',
      'entity' => 'SearchDisplay',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'Contributions_Table_1',
          'label' => 'Contributions Table 1',
          'saved_search_id.name' => 'Quotation_Contributions',
          'type' => 'table',
          'settings' => [
            'actions' => TRUE,
            'limit' => 10,
            'classes' => [
              'table',
              'table-striped',
            ],
            'pager' => [],
            'placeholder' => 5,
            'sort' => [],
            'columns' => [
              [
                'type' => 'field',
                'key' => 'total_amount',
                'dataType' => 'Money',
                'label' => 'Amount',
                'sortable' => TRUE,
              ],
              [
                'type' => 'field',
                'key' => 'financial_type_id:label',
                'dataType' => 'Integer',
                'label' => 'Type',
                'sortable' => TRUE,
              ],
              [
                'type' => 'field',
                'key' => 'source',
                'dataType' => 'String',
                'label' => 'Source',
                'sortable' => TRUE,
              ],
              [
                'type' => 'field',
                'key' => 'receive_date',
                'dataType' => 'Timestamp',
                'label' => 'Date',
                'sortable' => TRUE,
              ],
              [
                'type' => 'field',
                'key' => 'thankyou_date',
                'dataType' => 'Timestamp',
                'label' => 'Thank-you Sent',
                'sortable' => TRUE,
                'rewrite' => '{if "[thankyou_date]"} Yes {else} No {/if}',
              ],
              [
                'type' => 'field',
                'key' => 'contribution_status_id:label',
                'dataType' => 'Integer',
                'label' => 'Status',
                'sortable' => TRUE,
              ],
              [
                'text' => 'Actions',
                'style' => 'default',
                'size' => 'btn-sm',
                'icon' => 'fa-bars',
                'links' => [
                  [
                    'entity' => 'Contribution',
                    'action' => 'view',
                    'join' => '',
                    'target' => 'crm-popup',
                    'icon' => 'fa-external-link',
                    'text' => 'View',
                    'style' => 'default',
                    'path' => '',
                    'condition' => [],
                  ],
                  [
                    'entity' => 'Contribution',
                    'action' => 'update',
                    'join' => '',
                    'target' => 'crm-popup',
                    'icon' => 'fa-pencil',
                    'text' => 'Edit',
                    'style' => 'default',
                    'path' => '',
                    'condition' => [],
                  ],
                  [
                    'entity' => '',
                    'action' => '',
                    'join' => '',
                    'target' => 'crm-popup',
                    'icon' => 'fa-paper-plane-o',
                    'text' => 'Send By Email',
                    'style' => 'default',
                    'path' => 'civicrm/contribute/invoice/email/?reset=1&id=[id]&select=email&cid=[contact_id]',
                    'condition' => [],
                  ],
                ],
                'type' => 'menu',
                'alignment' => 'text-right',
              ],
            ],
            'noResultsText' => 'No Invoices found',
          ],
          'acl_bypass' => FALSE,
        ],
      ],
    ],
];

$searchKitIsInstalled = 'installed' ===
CRM_Extension_System::singleton()->getManager()->getStatus('org.civicrm.search_kit');
if ($searchKitIsInstalled) {
  return $mgd;
}

return [];
