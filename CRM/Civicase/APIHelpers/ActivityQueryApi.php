<?php

use CRM_Civicase_APIHelpers_GenericApi as GenericApiHelper;

/**
 * ActivityQueryApi API Helper Class.
 */
class CRM_Civicase_APIHelpers_ActivityQueryApi {

  /**
   * Validates the Case Query related API parameters.
   *
   * @param array $params
   *   API parameters.
   */
  public function validateParameters(array $params) {
    if (!empty($params['params']) && !empty($params['id'])) {
      throw new API_Exception("Please send either the params or Id");
    }

    if (empty($params['params']) && empty($params['id'])) {
      throw new API_Exception("Both params and Id cannot be empty");
    }
  }

  /**
   * Returns the parameters for making calls to Activity.get.
   *
   * @param array $params
   *   API parameters.
   *
   * @return array|string
   *   The parameters for making call to Activity.get.
   */
  public function getActivityGetRequestApiParams(array $params) {
    $genericApiHelper = new GenericApiHelper();
    $apiParams = '';

    if (!empty($params['id'])) {
      $apiParams = ['id' => ['IN' => $genericApiHelper->getParameterValue($params, 'id')]];
    }

    if (!empty($params['params'])) {
      $apiParams = $params['params'];
    }

    return $apiParams;
  }

  /**
   * Copy tags from one activity to another activity.
   *
   * @param int $activityId
   *   ID of the activity that is being copied.
   * @param int $newActivityId
   *   ID of the new activity.
   * @param string $mode
   *   Mode can either be move or copy.
   */
  public function transferActivityTags($activityId, $newActivityId, $mode = 'copy') {
    $result = civicrm_api3('EntityTag', 'get', [
      'sequential' => 1,
      'entity_table' => "civicrm_activity",
      'entity_id' => $activityId,
    ]);

    array_walk($result['values'], function ($tag) use ($newActivityId, $mode) {
      $tag['entity_id'] = $newActivityId;
      if ($mode === 'copy') {
        unset($tag['id']);
      }

      civicrm_api3('EntityTag', 'create', $tag);
    });
  }

  /**
   * Retirns all file activities for a case.
   *
   * @param int $caseId
   *   Case id.
   *
   * @return array
   *   Id of all file activities.
   *
   * @throws \Civi\Core\Exception\DBQueryException
   */
  public function getFileActivities(int $caseId): array {
    $query = '
           SELECT
               ca.id AS id
           FROM
               civicrm_case_activity cca
           INNER JOIN civicrm_activity ca
               ON ca.id = cca.activity_id
           INNER JOIN civicrm_entity_file cef
               ON (cef.entity_table = "civicrm_activity" AND cef.entity_id = ca.id)
           WHERE
               cca.case_id= %1
       ';

    $queryParams = [1 => [$caseId, 'Integer']];
    $dao = CRM_Core_DAO::executeQuery($query, $queryParams);

    return $dao->fetchAll();
  }

  /**
   * Add target contacts of an activity to params array.
   *
   * @param int $activityId
   *   Activity id.
   * @param array $params
   *   All case params.
   */
  public function addTargetContactsToParams(int $activityId, array &$params): void {
    $recordTypes = CRM_Activity_BAO_ActivityContact::buildOptions('record_type_id', 'validate');
    $targetID = CRM_Utils_Array::key('Activity Targets', $recordTypes);
    $targetContactIds = array_keys(CRM_Activity_BAO_ActivityContact::getNames($activityId, $targetID));
    if (!empty($targetContactIds)) {
      $params['targetContactIds'] = implode(',', $targetContactIds);
    }
  }

}
