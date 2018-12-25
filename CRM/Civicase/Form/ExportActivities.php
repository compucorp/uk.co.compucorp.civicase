<?php

class CRM_Civicase_Form_ExportActivities extends CRM_Core_Form {
  public function buildQuickForm() {
    parent::buildQuickForm();

    $this->addElement('text', 'activities', 'Activities');

    $this->addButtons(
      array(
        array(
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ),
        array(
          'type' => 'submit',
          'name' => ts('Save'),
          'isDefault' => TRUE,
        ),
      )
    );
  }

  public function postProcess() {
    $activityIds = CRM_Utils_Request::retrieve('activities', 'String');
    $activityIds = explode(',', $activityIds);

    $activities = civicrm_api3('Activity', 'get', [
      'id' => ['IN' => $activityIds],
    ]);

    $form = (object) [
      '_columnHeaders' => [
        'id' => ['title' => 'The ID of the activity'],
        'subject' => ['title' => 'Subject'],
      ]
    ];

    CRM_Report_Utils_Report::export2csv($form, $activities['values']);
  }
}
