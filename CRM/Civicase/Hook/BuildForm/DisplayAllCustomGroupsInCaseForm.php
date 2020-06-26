<?php

/**
 * Display all custom groups in case form hook.
 */
class CRM_Civicase_Hook_BuildForm_DisplayAllCustomGroupsInCaseForm {

  /**
   * The name for the Case entity.
   */
  const CASE_ENTITY_NAME = 'Case';

  /**
   * Adds all custom groups (inline/tabs) to the case form.
   *
   * @param CRM_Core_Form $form
   *   CiviCRM Form.
   */
  public function run(CRM_Core_Form $form) {
    if (!$this->shouldRun($form)) {
      return;
    }

    $caseId = CRM_Utils_Request::retrieve('entityID', 'Positive');
    $caseType = CRM_Utils_Request::retrieve('subType', 'Positive');
    $customGroups = CRM_Civicase_APIHelpers_CustomGroups::getAllActiveGroupsForEntity(
      self::CASE_ENTITY_NAME
    );
    $formattedCustomGroups = [];

    foreach ($customGroups['values'] as $customGroup) {
      // Will return the right custom fields for the given case type.
      // Fields belonging to other case types are removed.
      $customGroupTree = CRM_Core_BAO_CustomGroup::getTree(
        self::CASE_ENTITY_NAME,
        NULL,
        $caseId,
        $customGroup['id'],
        $caseType
      );

      // Removes extra data not used for displaying the custom fields:
      $customGroupTree = CRM_Core_BAO_CustomGroup::formatGroupTree($customGroupTree);
      unset($customGroupTree['info']);

      $formattedCustomGroups = array_merge($formattedCustomGroups, $customGroupTree);
    }

    // Adds the actual field element to each custom field:
    CRM_Core_BAO_CustomGroup::buildQuickForm($form, $formattedCustomGroups);

    $form->setVar('_groupTree', $formattedCustomGroups);
  }

  /**
   * Determines if the hook should run.
   *
   * @param CRM_Core_Form $form
   *   CiviCRM Form.
   *
   * @return bool
   *   True when updating the case form's custom groups.
   */
  private function shouldRun(CRM_Core_Form $form) {
    $isCaseEntity = $form->getVar('_type') === self::CASE_ENTITY_NAME;
    $isCustomDataForm = get_class($form) === CRM_Custom_Form_CustomDataByType::class;

    return $isCaseEntity && $isCustomDataForm;
  }

}
