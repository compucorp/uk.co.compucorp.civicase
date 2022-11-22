<?php

/**
 * @file
 * Activity.GetAll file.
 */

/**
 * Activity.GetAll API specification.
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_activity_getall_spec(array &$spec) {
  $spec = civicrm_api3('Activity', 'getfields')['values'];
}

/**
 * Activity.GetAll API.
 *
 * When adding 'target_contact_name' or 'assignee_contact_name' to
 * civicrm “Activity.get” API, it will fetch all the target and assignee
 * contacts for each activity, but certain known type of activities such as
 * "Bulk Email" and "Bulk SMS" are known to have large number of target
 * activities, which results in slow queries.
 * This API is similar to “Activity.get”, the difference
 * is that it does not fetch the target and assignee contacts for
 * activity types that are known to have large number of contacts.
 *
 * @param array $params
 *   API parameters.
 *
 * @return array
 *   Activities
 */
function civicrm_api3_activity_getall(array $params) {
  $contactTypesToReturn = [];
  $params['return'] = is_string($params['return']) ? explode(',', $params['return']) : $params['return'];

  $targetContactParamIndex = array_search('target_contact_name', $params['return']);
  if ($targetContactParamIndex !== FALSE) {
    $contactTypesToReturn['target_contact_name'] = 1;
    unset($params['return'][$targetContactParamIndex]);
  }

  $assigneeContactParamIndex = array_search('assignee_contact_name', $params['return']);
  if ($assigneeContactParamIndex !== FALSE) {
    $contactTypesToReturn['assignee_contact_name'] = 1;
    unset($params['return'][$assigneeContactParamIndex]);
  }

  $params['return'] = implode(',', $params['return']);
  $result = civicrm_api3('Activity', 'get', $params);

  // These two activity types are known to often have a lot
  // of contacts attached to them (mostly as target contacts),
  // thus we ignore fetching contacts for them, since for
  // example the user can just open the mailing report to see
  // the contacts who received the email.
  $bulkEmailActivityId = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Bulk Email');
  $massSmsActivityId = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Mass SMS');
  $activityTypesToIgnore = [$bulkEmailActivityId, $massSmsActivityId];

  $activityIdsToFetchContactsFor = [];
  foreach ($result['values'] as $row) {
    if (!in_array($row['activity_type_id'], $activityTypesToIgnore)) {
      $activityIdsToFetchContactsFor[$row['id']] = [];
    }
  }

  // Here we fetch the contacts separately, but only for
  // the activities that are not of the types defined above.
  if (!empty($activityIdsToFetchContactsFor)) {
    _civicrm_api3_activity_fill_activity_contact_names($activityIdsToFetchContactsFor, $params, $contactTypesToReturn);
  }

  // Merging the activity contacts back to the activities
  // we fetched earlier.
  foreach ($result['values'] as &$row) {
    if (!empty($activityIdsToFetchContactsFor[$row['id']])) {
      $row = array_merge($activityIdsToFetchContactsFor[$row['id']], $row);
    }
  }

  return $result;
}
