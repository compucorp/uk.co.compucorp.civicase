<?php

/**
 * Allows selecting the display style for custom fields targeting cases.
 *
 * Normally these field is only available for contact related entities.
 */
class CRM_Civicase_Hook_BuildForm_AddStyleFieldToCaseCustomGroups {

  /**
   * Includes "Case" in the list of entities that suppport the "style" field.
   *
   * For core these entities are exclusively related to contacts, hence we need
   * to override the `contactTypes` template var.
   *
   * We also include a JS file so we can hide an extra display option
   * (tab table) that is not by the Case entity, but added manually by Core.
   *
   * @param object $form
   *   The current form's reference.
   * @param string $formName
   *   The name of the current form.
   */
  public function run($form, $formName) {
    if (!$this->shouldRun($form)) {
      return;
    }

    $contactTypes = json_decode($form->get_template_vars('contactTypes'));
    $contactTypes[] = 'Case';

    $form->assign('contactTypes', json_encode($contactTypes));

    CRM_Core_Resources::singleton()
      ->addScriptFile('uk.co.compucorp.civicase', 'js/custom-group-form.js');
  }

  /**
   * Runs only when using the custom group form.
   *
   * @return bool
   *   true when using the custom group form.
   */
  private function shouldRun($form) {
    return get_class($form) === CRM_Custom_Form_Group::class;
  }

}
