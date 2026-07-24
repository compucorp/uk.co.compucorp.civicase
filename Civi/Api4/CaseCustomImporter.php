<?php

namespace Civi\Api4;

use Civi\Api4\Generic\AbstractEntity;
use Civi\Api4\Generic\BasicGetFieldsAction;
use CRM_Civicase_Service_RepeatableCaseCustomImporter as Importer;

/**
 * CaseCustomImporter API4 entity (TCOSB-64 / 1.8).
 *
 * A code-only entity that exposes the importer's field metadata over APIv4, so
 * tools that introspect the import entity via api4 (the nz.co.fuzion.csvimport
 * "API Import" screen calls APIv4 getFields on it) can resolve it. The per-row
 * import itself runs through the api3 CaseCustomImporter.create action, backed
 * by CRM_Civicase_Service_RepeatableCaseCustomImporter.
 *
 * @package Civi\Api4
 */
class CaseCustomImporter extends AbstractEntity {

  /**
   * APIv4 field metadata for the importer.
   *
   * @param bool $checkPermissions
   *   Whether to enforce permissions.
   *
   * @return \Civi\Api4\Generic\BasicGetFieldsAction
   *   Lists case_id plus every repeatable Case custom field.
   */
  public static function getFields($checkPermissions = TRUE) {
    $action = new BasicGetFieldsAction(
      'CaseCustomImporter',
      __FUNCTION__,
      [static::class, 'fieldList']
    );
    return $action->setCheckPermissions($checkPermissions);
  }

  /**
   * The importable field definitions (case_id + each repeatable custom field).
   *
   * @return array
   *   APIv4 field spec array.
   */
  public static function fieldList(): array {
    $fields = [
      ['name' => 'case_id', 'title' => ts('Case ID'), 'data_type' => 'Integer'],
      ['name' => 'id', 'title' => ts('Record ID'), 'data_type' => 'Integer'],
    ];
    foreach (Importer::mappableFields() as $key => $label) {
      $fields[] = ['name' => $key, 'title' => $label, 'data_type' => 'String'];
    }
    return $fields;
  }

  /**
   * Per-action permissions.
   *
   * @return array
   *   Permissions required per action.
   */
  public static function permissions() {
    return ['default' => ['access CiviCRM']];
  }

}
