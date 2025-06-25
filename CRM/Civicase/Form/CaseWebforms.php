<?php

/**
 * This class generates form components for Civicase webforms.
 */
class CRM_Civicase_Form_CaseWebforms extends CRM_Admin_Form {

  /**
   * The case related webforms.
   *
   * @var array
   */
  private $webforms = [];

  /**
   * Builds the form object.
   */
  public function buildQuickForm() {
    parent::buildQuickForm();

    $webforms = civicrm_api3('Case', 'getwebforms');
    $errorMsg = NULL;
    $webformids = [];

    if (isset($webforms['values']) && count($webforms['values'])) {
      foreach ($webforms['values'] as $item) {
        $webformids[] = 'webforms_' . $item['nid'];
        $this->add('checkbox', 'webforms_' . $item['nid'], $item['title']);
        $this->webforms[$item['nid']] = $item;
      }
    }

    if (isset($webforms['warning_message'])) {
      $errorMsg = $webforms['warning_message'];
    }
    elseif (count($webformids) == 0) {
      $errorMsg = ts('No Webforms with cases exists!');
    }

    $this->assign('nids', $webformids);
    $this->assign('errorMsg', $errorMsg);

    $this->addButtons(
      [
        [
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ],
        [
          'type' => 'submit',
          'name' => ts('Save'),
          'isDefault' => TRUE,
        ],
      ]
    );
  }

  /**
   * Sets defaults for form.
   *
   * @see CRM_Core_Form::setDefaultValues()
   */
  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();

    $items = Civi::settings()->get('civi_drupal_webforms');
    if (isset($items)) {
      foreach ($items as $item) {
        $defaults['webforms_' . $item['nid']] = 1;
      }
    }

    return $defaults;
  }

  /**
   * PostProcess function.
   */
  public function postProcess() {
    $values = $this->getSubmitValues();
    $items = [];
    foreach ($values as $k => $value) {
      if (strpos($k, 'webforms_') === 0) {
        $id = substr($k, 9);
        $items[] = $this->webforms[$id];
      }
    }
    Civi::settings()->set('civi_drupal_webforms', $items);
    CRM_Core_Session::setStatus(ts('Your changes have been saved successfully.'), 'Case Webforms', 'success');
  }

  /**
   * Explicitly declare the entity api name.
   */
  public function getDefaultEntity() {
    return 'Case';
  }

}
