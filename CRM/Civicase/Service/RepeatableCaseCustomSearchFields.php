<?php

/**
 * Search metadata for fields of repeatable (multi-record) Case custom groups.
 */
class CRM_Civicase_Service_RepeatableCaseCustomSearchFields {

  /**
   * Gets the search metadata of repeatable Case custom fields.
   *
   * Uses the same permission and active-state rules as core's Case fields
   * (CRM_Core_BAO_CustomField::getFieldsForImport defaults), so ACLs on custom
   * groups are respected.
   *
   * @return array
   *   Field metadata keyed by `custom_<id>`, in the format core uses for Case
   *   search fields (`table_name`, `column_name`, `where`, `title`, ...).
   */
  public static function getFields(): array {
    $fields = CRM_Core_BAO_CustomField::getFieldsForImport('Case', FALSE, FALSE, TRUE, TRUE, TRUE);

    return array_filter($fields, function (array $field): bool {
      return !empty($field['is_multiple']);
    });
  }

}
