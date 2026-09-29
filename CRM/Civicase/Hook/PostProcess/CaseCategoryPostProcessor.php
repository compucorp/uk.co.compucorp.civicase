<?php

use CRM_Civicase_Factory_CaseTypeCategoryEventHandler as CaseTypeCategoryEventHandlerFactory;
use CRM_Civicase_Helper_CaseCategory as CaseTypeCategoryHelper;

/**
 * Handles post processing for the case category form.
 */
class CRM_Civicase_Hook_PostProcess_CaseCategoryPostProcessor {

  /**
   * Case Category Menu Links Processor.
   *
   * Creates/Deletes menus for the Case category option group is saved/deleted
   * based on the form action.
   *
   * @param string $formName
   *   Form name.
   * @param CRM_Core_Form $form
   *   Form object class.
   */
  public function run($formName, CRM_Core_Form &$form) {
    if (!$this->shouldRun($form, $formName)) {
      return;
    }

    // Get object data from submitted from.
    $formValues = $form->_submitValues;
    $caseCategoryValues = $form->getVar('_values');
    $caseCategory = [
      'id' => $form->getVar('_id'),
      'name' => $formValues['label'],
      'label' => $formValues['label'],
      'singular_label' => $formValues['singular_label'],
      'is_active' => $formValues['is_active'],
      'icon' => $formValues['icon'],
    ];
    $formAction = $form->getVar('_action');
    $categoryValue = !empty($caseCategoryValues['value']) ? $caseCategoryValues['value'] : $formValues['value'];
    $caseCategoryInstance = CaseTypeCategoryHelper::getInstanceObject($categoryValue);
    $handler = CaseTypeCategoryEventHandlerFactory::create();

    if ($formAction == CRM_Core_Action::UPDATE) {
      $handler->onUpdate($caseCategoryInstance, $caseCategory);
    }
    elseif ($formAction == CRM_Core_Action::ADD) {
      $handler->onCreate($caseCategoryInstance, $caseCategory);
    }
    elseif ($formAction == CRM_Core_Action::DELETE) {
      $handler->onDelete($caseCategoryInstance, $caseCategory);
    }

    // Flush all caches using the API.
    civicrm_api3('System', 'flush');
  }

  /**
   * Determines if the hook will run.
   *
   * @param CRM_Core_Form $form
   *   Form object class.
   * @param string $formName
   *   Form name.
   *
   * @return bool
   *   returns TRUE or FALSE.
   */
  private function shouldRun(CRM_Core_Form $form, $formName) {
    // Form name first: only the Options form has _gName.
    return $formName == 'CRM_Admin_Form_Options'
      && $form->getVar('_gName') == 'case_type_categories';
  }

}
