<?php

return [
  [
    'name' => 'SavedSearch_Quotations', 
    'entity' => 'SavedSearch', 
    'cleanup' => 'unused', 
    'update' => 'unmodified',
    'params' => [
      'version' => 4, 
      'values' => [
        'name' => 'Quotations', 
        'label' => 'Quotations', 
        'form_values' => NULL, 
        'search_custom_id' => NULL, 
        'api_entity' => 'CaseSalesOrder', 
        'api_params' => [
          'version' => 4, 
          'select' => [
            'id', 
            'client_id.display_name', 
            'owner_id.display_name', 
            'currency:label', 
            'total_before_tax', 
            'total_after_tax',
          ], 
          'orderBy' => [], 
          'where' => [
            [
              'case_id.subject', 
              'IS EMPTY',
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
    'match' => ['name', 'api_entity']
  ],
];
