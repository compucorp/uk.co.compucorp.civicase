<?php

use Civi\Api4\CustomGroup;
use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Hides Case custom groups from core's "Import Multi-value Custom Data".
 */
class CRM_Civicase_Hook_BuildForm_HideCaseGroupsFromCustomDataImport {

  /**
   * The form that picks the group to import into.
   */
  const FORM_NAME = 'CRM_Custom_Import_Form_DataSource';

  /**
   * The form's group select.
   */
  const ELEMENT_NAME = 'multipleCustomData';

  /**
   * Removes Case groups from the group select.
   *
   * @param CRM_Core_Form $form
   *   Form object.
   * @param string $formName
   *   Form name.
   */
  public function run(CRM_Core_Form &$form, $formName) {
    if ($formName !== self::FORM_NAME || !$form->elementExists(self::ELEMENT_NAME)) {
      return;
    }

    $element = $form->getElement(self::ELEMENT_NAME);
    if (!$element instanceof HTML_QuickForm_select) {
      return;
    }

    $caseGroupIds = self::getCaseGroupIds();
    $element->_options = array_values(array_filter(
      $element->_options,
      fn ($option) => !in_array((int) ($option['attr']['value'] ?? 0), $caseGroupIds, TRUE)
    ));
  }

  /**
   * IDs of the multi-record custom groups that extend Cases.
   *
   * @return int[]
   *   Custom group IDs.
   */
  public static function getCaseGroupIds(): array {
    $ids = CustomGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('is_multiple', '=', TRUE)
      ->addWhere('extends', 'IN', CaseCustomAfforms::getCaseExtends())
      ->execute()
      ->column('id');

    return array_map('intval', $ids);
  }

}
