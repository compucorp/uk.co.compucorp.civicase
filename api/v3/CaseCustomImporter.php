<?php

/**
 * @file
 * CaseCustomImporter.create API.
 *
 * A thin importer endpoint called once per CSV row by the
 * nz.co.fuzion.csvimport extension ("CSV Import to API"). It creates and
 * updates repeatable Case custom data additively — no core Import framework
 * changes. The parent Case is matched by Case ID; per group, a blank Custom
 * Value ID creates a record and a populated one updates that record. See
 * CRM_Civicase_Service_RepeatableCaseCustomImporter.
 */

use CRM_Civicase_Service_RepeatableCaseCustomImporter as Importer;

/**
 * CaseCustomImporter.create.
 *
 * @param array $params
 *   Row values (case_id, custom_value_id_<groupId> and custom_<fieldId>
 *   columns).
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
  // Each group's record ID is taken as text, so the importer can name the
  // group in its own message when the cell is not a positive whole number.
  foreach (Importer::recordIdFields() as $key => $label) {
    $spec[$key] = [
      'title' => $label,
      'type' => CRM_Utils_Type::T_STRING,
    ];
  }
  $parserMetadata = Importer::parserFieldMetadata();
  foreach (Importer::mappableFields() as $key => $label) {
    $spec[$key] = [
      'title' => $label,
      'type' => CRM_Utils_Type::T_STRING,
    ] + ($parserMetadata[$key] ?? []);
  }
}

/**
 * DAO for the CaseCustomImporter entity, which has no table of its own.
 *
 * Lets the CSV Import to API extension's "Allow Updating An Entity Using
 * Unique Fields" option run instead of failing; see the class for why it
 * then has no effect.
 *
 * @return string
 *   The stand-in DAO class name.
 */
function _civicrm_api3_case_custom_importer_DAO() {
  return CRM_Civicase_Service_RepeatableCaseCustomImporterDAO::class;
}
