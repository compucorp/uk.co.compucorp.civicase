<?php

/**
 * @file
 * Activity.CopyByQuery file.
 */

/**
 * Activity.CopyByQuery API specification.
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_activity_copybyquery_spec(array &$spec) {
  $spec['case_id'] = [
    'title' => 'Case ID',
    'api.required' => 1,
    'description' => 'Activity ID',
    'type' => CRM_Utils_Type::T_INT,
  ];
  $spec['id'] = [
    'title' => 'Activity ID',
    'description' => 'Activity ID',
  ];
  $spec['params'] = [
    'title' => 'Params for Activity Get',
    'description' => 'Array of parameters for Activity.Get API',
    'type' => CRM_Utils_Type::T_STRING,
  ];
}

/**
 * Activity.Copybyquery API.
 *
 * This API uses copies activities and creates new activities
 * with the case ID sent. The activity ID's to be copied can be sent in the id
 * parameter or they can be fetched with a call to Activity.get using
 * the parameters sent in params to query Activity.get API.
 *
 * @param array $params
 *   API parameters.
 *
 * @return array
 *   API result descriptor
 */
function civicrm_api3_activity_copybyquery(array $params) {
  $activityQueryApiHelper = new CRM_Civicase_APIHelpers_ActivityQueryApi();
  $activityQueryApiHelper->validateParameters($params);
  $activityApiParams = $activityQueryApiHelper->getActivityGetRequestApiParams($params);
  $genericApiHelper = new CRM_Civicase_APIHelpers_GenericApi();
  $activities = $genericApiHelper->getEntityValues('Activity', $activityApiParams);

  $activityIds = [];
  foreach ($activities as $activity) {
    unset($activity['id']);
    $activity['case_id'] = $params['case_id'];
    $result = civicrm_api3('Activity', 'create', $activity);
    $activityIds[] = $result['id'];
  }

  return civicrm_api3_create_success($activityIds, $params, 'Activity', 'copybyquery');
}
