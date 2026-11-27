<?php

/**
 * @file
 * Styles for the repeatable Case custom data add/edit afforms (TCOSB-51).
 *
 * Required by the generated afformCreateCustom_<group> /
 * afformUpdateCustom_<group> forms (see
 * CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms::buildForm), so the
 * styles load wherever those forms render: the standalone route and the
 * crm-popup opened from the case tab. No base pages: it only loads on demand.
 *
 * Also loaded with afform itself, so Form Builder forms with repeatable Case
 * groups explain invalid fields the same way; see
 * CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAfform.
 */

return [
  'js' => ['ang/civicase-repeatable-custom-data-form.js'],
  'css' => ['ang/civicase-repeatable-custom-data-form/form.css'],
  'requires' => [],
  'basePages' => [],
  'settingsFactory' => [
    'CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAfform',
    'getAngularSettings',
  ],
];
