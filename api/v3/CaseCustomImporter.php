<?php

/**
 * @file
 * CaseCustomImporter.create API (TCOSB-64 / 1.8).
 *
 * A thin importer endpoint called once per CSV row by the
 * nz.co.fuzion.csvimport extension ("CSV GUI Import to api"). It creates
 * repeatable Case custom data additively — no core Import framework changes.
 * The parent Case is matched by Case ID; each row creates new record(s)
 * (create-only). See CRM_Civicase_Service_RepeatableCaseCustomImporter.
 */

use CRM_Civicase_Service_RepeatableCaseCustomImporter as Importer;

/**
 * CaseCustomImporter.create.
 *
 * @param array $params
 *   Row values (case_id + custom_<fieldId> columns).
 *
 * @return array
 *   API3 result.
 */
function civicrm_api3_case_custom_importer_create($params) {
  try {
    $created = Importer::importRow($params);
    return civicrm_api3_create_success($created, $params);
  }
  catch (Exception $exception) {
    return civicrm_api3_create_error($exception->getMessage());
  }
}

/**
 * Field metadata for CaseCustomImporter.create (drives the CSV column mapping).
 *
 * @param array $spec
 *   The spec, by reference.
 */
function _civicrm_api3_case_custom_importer_create_spec(&$spec) {
  $spec['case_id'] = [
    'title' => ts('Case ID'),
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  ];
  foreach (Importer::mappableFields() as $key => $label) {
    $spec[$key] = [
      'title' => $label,
      'type' => CRM_Utils_Type::T_STRING,
    ];
  }
}
