<?php

/**
 * Class CRM_Civicase_Service_CaseRoleCreationPreProcess.
 */
class CRM_Civicase_Service_CaseRoleCreationPreProcess extends CRM_Civicase_Service_CaseRoleCreationBase {

  /**
   * Function that handles pre-processing for a case related relationship.
   *
   * Basically, when the single case role per type setting is on and the
   * multiclient case setting is off, the start date for a case related
   * relationship is set to today's date.
   *
   * @param array $requestParams
   *   API request parameters.
   */
  public function onCreate(array &$requestParams) {
    if ($this->isSingleCaseRole && !$this->isMultiClient) {
      $requestParams['params']['start_date'] = date('Y-m-d');
    }

  }

}
