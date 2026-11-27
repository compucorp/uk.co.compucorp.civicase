<?php

use CRM_Civicase_Service_RepeatableCaseCustomSearchFields as RepeatableCaseCustomSearchFields;

/**
 * Lets the legacy search query understand repeatable Case custom fields.
 */
class CRM_Civicase_BAO_Query_RepeatableCaseCustomFields extends CRM_Contact_BAO_Query_Interface {

  /**
   * {@inheritDoc}
   */
  public function &getFields() {
    $fields = [];

    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public static function from($fieldName, $mode, $side) {
    return '';
  }

  /**
   * Adds metadata for the repeatable Case fields used by the search.
   *
   * @param CRM_Contact_BAO_Query $query
   *   The query being built.
   */
  public static function select(&$query) {
    if (empty($query->_cfIDs)) {
      return;
    }

    $repeatableFields = RepeatableCaseCustomSearchFields::getFields();
    foreach (array_keys($query->_cfIDs) as $fieldId) {
      $key = 'custom_' . $fieldId;
      if (isset($repeatableFields[$key]) && !isset($query->_fields[$key])) {
        $query->_fields[$key] = $repeatableFields[$key];
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getPanesMapper(&$panes) {
  }

}
