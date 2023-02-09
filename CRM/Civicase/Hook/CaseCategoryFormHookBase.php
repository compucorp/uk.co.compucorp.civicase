<?php

/**
 * Base class for the Case Category Form hook classes.
 *
 * This is implemented by hook classes responding to changes
 * on the case category form page. It defines common functions
 * that can be shared by the hook classes.
 */
class CRM_Civicase_Hook_CaseCategoryFormHookBase {

  /**
   * Instance field name.
   */
  const INSTANCE_TYPE_FIELD_NAME = 'case_category_instance_type';

  /**
   * Instance field name.
   */
  const FEATURES_FIELD_NAME = 'case_category_features';

  /**
   * Determines if the given form is a case type categories form.
   *
   * @param CRM_Core_Form $form
   *   Form object class.
   * @param string $formName
   *   Form name.
   *
   * @return bool
   *   True if the form is right.
   */
  protected function isCaseTypeCategoriesForm(CRM_Core_Form $form, $formName) {
    $optionGroupName = $form->getVar('_gName');

    return $formName == CRM_Admin_Form_Options::class
      && $optionGroupName == 'case_type_categories';
  }

  /**
   * Determines the condition for the hook to run.
   *
   * The hook will run when the option value for the case type category
   * option group is being created or edited.
   *
   * @param CRM_Core_Form $form
   *   Form object class.
   * @param string $formName
   *   Form name.
   *
   * @return bool
   *   True if the hook class should run.
   */
  protected function shouldRun(CRM_Core_Form $form, $formName) {
    $formAction = $form->getVar('_action');

    return $this->isCaseTypeCategoriesForm($form, $formName)
      && $formAction != CRM_Core_Action::DELETE;
  }

}
