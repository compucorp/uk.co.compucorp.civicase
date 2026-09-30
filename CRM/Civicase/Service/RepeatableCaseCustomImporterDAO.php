<?php

/**
 * Stand-in DAO for the CaseCustomImporter API entity (TCOSB-64 / 1.8).
 *
 * CaseCustomImporter has no table of its own: each CSV row is written to the
 * repeatable Case custom groups' own tables. The CSV Import to API extension
 * (nz.co.fuzion.csvimport) still asks the import entity's DAO for its unique
 * indices when "Allow Updating An Entity Using Unique Fields" is ticked, and
 * fails when the entity has no DAO. With no fields and no indices, that option
 * finds nothing to match on, so the import behaves the same ticked or
 * unticked. Updates are driven by the mapped record ID, as a mapped ID is for
 * any other entity.
 */
class CRM_Civicase_Service_RepeatableCaseCustomImporterDAO extends CRM_Core_DAO {

  /**
   * No stored fields: the importer's columns come from its API spec.
   *
   * @return array
   *   An empty field list.
   */
  public static function &fields() {
    $fields = [];
    return $fields;
  }

  /**
   * No indices, so there are no unique fields to match existing records on.
   *
   * @param bool $localize
   *   Unused; matches the generated-DAO signature.
   *
   * @return array
   *   An empty index list.
   */
  public static function indices($localize = TRUE) {
    return [];
  }

}
