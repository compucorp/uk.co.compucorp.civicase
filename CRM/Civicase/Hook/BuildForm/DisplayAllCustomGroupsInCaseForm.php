<?php

/**
 * Display all custom groups in case form hook.
 */
class CRM_Civicase_Hook_BuildForm_DisplayAllCustomGroupsInCaseForm {

  /**
   * Adds all custom groups (inline/tabs) to the case form.
   *
   * @param CRM_Core_Form $from
   *   CiviCRM Form
   */
  public function run ($form) {
    if (!$this->shouldRun($form)) {
      return;
    }

    $caseCategory = $form->getVar('_type');
    $caseId = CRM_Utils_Request::retrieve('entityID', 'Positive');
    $caseType = CRM_Utils_Request::retrieve('subType', 'Positive');
    $customGroups = CRM_Civicase_APIHelpers_CustomGroups::getAllActiveGroupsForEntity($caseCategory);
    $formattedCustomGroups = [];

    foreach ($customGroups['values'] as $customGroup) {
      $customGroupsTree = CRM_Core_BAO_CustomGroup::getTree(
        $caseCategory,
        NULL,
        $caseId,
        $customGroup['id'],
        $caseType
      );
      $customGroupsTree = CRM_Core_BAO_CustomGroup::formatGroupTree($customGroupsTree);
      unset($customGroupsTree['info']);
      $formattedCustomGroups = array_merge($formattedCustomGroups, $customGroupsTree);
    }

    // Adds the actual field element to each custom field:
    CRM_Core_BAO_CustomGroup::buildQuickForm($form, $formattedCustomGroups);

    $form->setVar('_groupTree', $formattedCustomGroups);
  }

  /**
   * Determines if the hook should run.
   *
   * @param CRM_Core_Form $from
   *   CiviCRM Form
   *
   * @return bool
   *   True when updating the case form's custom groups.
   */
  private function shouldRun ($form) {
    $isCaseEntity = CRM_Utils_Request::retrieve('type', 'String') === 'Case';
    $isCustomDataForm = get_class($form) === CRM_Custom_Form_CustomDataByType::class;

    return $isCaseEntity && $isCustomDataForm;
  }
}
