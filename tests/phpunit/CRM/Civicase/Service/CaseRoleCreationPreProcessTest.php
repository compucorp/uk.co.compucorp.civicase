<?php

use CRM_Civicase_Service_CaseRoleCreationPreProcess as CaseRoleCreationPreProcess;

/**
 * Runs tests on CaseRoleCreationPreProcess Service tests.
 *
 * @group headless
 */
class CRM_Civicase_Service_CaseRoleCreationPreProcessTest extends BaseHeadlessTest {
  use CRM_Civicase_Helpers_CaseSettingsTrait;

  /**
   * Test start date is modified when single case role setting on.
   */
  public function testStartDateIsModifiedWhenSingleCaseRoleSettingIsOnAndMultiClientIsOff() {
    $this->setSingleCaseRoleSetting(TRUE);
    $this->setMultiClientCaseSetting(FALSE);

    $params = [
      'contact_id_b' => 1,
      'contact_id_a' => 2,
      'relationship_type_id' => 3,
      'case_id' => 2,
      'start_date' => '2020-6-05',
      // This will prevent the On respond event from being triggered.
      'skip_post_processing' => 1,
    ];
    $apiRequestParams = ['params' => $params];
    $caseRolePostProcess = new CaseRoleCreationPreProcess();
    $caseRolePostProcess->onCreate($apiRequestParams);

    $this->assertEquals(date('Y-m-d'), $apiRequestParams['params']['start_date']);
  }

  /**
   * Test start date not modified when single case role setting off.
   */
  public function testStartDateIsNotModifiedWhenSingleCaseRoleSettingIsOff() {
    $this->setSingleCaseRoleSetting(FALSE);

    $params = [
      'contact_id_b' => 1,
      'contact_id_a' => 2,
      'relationship_type_id' => 3,
      'case_id' => 2,
      'start_date' => '2020-06-05',
      // This will prevent the On respond event from being triggered.
      'skip_post_processing' => 1,
    ];
    $apiRequestParams = ['params' => $params];
    $caseRolePostProcess = new CaseRoleCreationPreProcess();
    $caseRolePostProcess->onCreate($apiRequestParams);

    $this->assertEquals($params['start_date'], $apiRequestParams['params']['start_date']);
  }

}
