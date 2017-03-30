<?php
require_once 'api/v3/Case.php';

/**
 * Case.getdetails API specification
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 */
function _civicrm_api3_case_getdetails_spec(&$spec) {
  $result = civicrm_api3('Case', 'getfields', array('api_action' => 'get'));
  $spec = $result['values'];
}

/**
 * Case.getdetails API
 *
 * This is provided by the CiviCase extension. It gives more robust output than the regular get action.
 *
 * @param array $params
 * @return array API result
 * @throws API_Exception
 */
function civicrm_api3_case_getdetails($params) {
  $params += array('return' => array());
  if (is_string($params['return'])) {
    $params['return'] = explode(',', str_replace(' ', '', $params['return']));
  }
  $toReturn = $params['return'];
  $options = CRM_Utils_Array::value('options', $params, array());
  $extraReturnProperties = array('activity_summary', 'last_update', 'activity_count');
  $params['return'] = array_diff($params['return'], $extraReturnProperties);

  // Support additional sort params
  $sql = _civicrm_api3_case_getdetails_extrasort($params);

  // Call the case api
  $result = civicrm_api3_case_get(array('sequential' => 0) + $params, $sql);
  if (!empty($result['values'])) {
    $ids = array_keys($result['values']);

    // Remove legacy cruft
    foreach ($result['values'] as &$case) {
      unset($case['client_id']);
    }

    // Get activity summary
    if (in_array('activity_summary', $toReturn)) {
      $catetoryLimits = CRM_Utils_Array::value('categories', $options, array_fill_keys(array('alert', 'milestone', 'task', 'communication'), 0));
      $categories = array_fill_keys(array_keys($catetoryLimits), array());
      foreach ($result['values'] as &$case) {
        $case['activity_summary'] = $categories + array('overdue' => array());
      }
      $allTypes = array();
      foreach (array_keys($categories) as $grouping) {
        $option = civicrm_api3('OptionValue', 'get', array(
          'return' => array('value'),
          'option_group_id' => 'activity_type',
          'grouping' => array('LIKE' => "%{$grouping}%"),
          'options' => array('limit' => 0),
        ));
        foreach ($option['values'] as $val) {
          $categories[$grouping][] = $allTypes[] = $val['value'];
        }
      }
      $activities = civicrm_api3('Activity', 'get', array(
        'return' => array('activity_type_id', 'subject', 'activity_date_time', 'status_id', 'case_id', 'assignee_contact_name'),
        'check_permissions' => !empty($params['check_permissions']),
        'case_id' => array('IN' => $ids),
        'is_current_revision' => 1,
        'is_test' => 0,
        'status_id' => array('NOT IN' => array('Completed', 'Cancelled')),
        'activity_type_id' => array('IN' => array_unique($allTypes)),
        'activity_date_time' => array('<' => 'now'),
        'options' => array(
          'limit' => 0,
          'sort' => 'activity_date_time',
          'or' => array(array('activity_date_time', 'activity_type_id')),
        ),
      ));
      foreach ($activities['values'] as $act) {
        $case =& $result['values'][$act['case_id']];
        unset($act['case_id']);
        foreach ($categories as $category => $grouping) {
          if (in_array($act['activity_type_id'], $grouping) && (!$catetoryLimits[$category] || count($case['activity_summary'][$category]) < $catetoryLimits[$category])) {
            $case['activity_summary'][$category][] = $act;
          }
        }
        if (strtotime($act['activity_date_time']) < time()) {
          $case['activity_summary']['overdue'][] = $act;
        }
      }
    }
    // Get activity count
    if (in_array('activity_count', $toReturn)) {
      foreach ($result['values'] as $id => &$case) {
        $query = "SELECT COUNT(a.id) as count, a.activity_type_id
          FROM civicrm_activity a
          INNER JOIN civicrm_case_activity ca ON ca.activity_id = a.id
          WHERE a.is_current_revision = 1 AND a.is_test = 0 AND ca.case_id = $id
          GROUP BY a.activity_type_id";
        $dao = CRM_Core_DAO::executeQuery($query);
        while ($dao->fetch()) {
          $case['activity_count'][$dao->activity_type_id] = $dao->count;
        }
      }
    }
    // Get last update
    if (in_array('last_update', $toReturn)) {
      // todo
    }
    if (!empty($params['sequential'])) {
      $result['values'] = array_values($result['values']);
    }
  }
  return $result;
}

/**
 * Support extra sorting in case.getdetails.
 *
 * @param $params
 * @return \CRM_Utils_SQL_Select
 * @throws \API_Exception
 */
function _civicrm_api3_case_getdetails_extrasort(&$params) {
  $sql = CRM_Utils_SQL_Select::fragment();
  $options = _civicrm_api3_get_options_from_params($params);

  if (!empty($options['sort'])) {
    $sort = explode(', ', $options['sort']);

    // For each one of our special fields we swap it for the placeholder (1) so it will be ignored by the case api.
    foreach ($sort as $index => &$sortString) {
      // Get sort field and direction
      list($sortField, $dir) = array_pad(explode(' ', $sortString), 2, 'ASC');
      list(, $sortField) = array_pad(explode('.', $sortField), 2, 'id');
      // Sort by case manager
      if (strpos($sortString, 'case_manager') === 0) {
        $caseTypeManagers = \Civi\CCase\Utils::getCaseManagerRelationshipTypes();
        // Validate inputs
        if (!array_key_exists($sortField, CRM_Contact_DAO_Contact::fieldKeys()) || ($dir != 'ASC' && $dir != 'DESC')) {
          throw new API_Exception("Unknown field specified for sort. Cannot order by '$sortString'");
        }
        $managerTypeClause = array();
        foreach ($caseTypeManagers as $caseTypeId => $relationshipTypeId) {
          $managerTypeClause[] = "(a.case_type_id = $caseTypeId AND manager_relationship.relationship_type_id = $relationshipTypeId)";
        }
        $managerTypeClause = implode(' OR ', $managerTypeClause);
        $sql->join('ccc', 'LEFT JOIN (SELECT * FROM civicrm_case_contact WHERE id IN (SELECT MIN(id) FROM civicrm_case_contact GROUP BY case_id)) AS ccc ON ccc.case_id = a.id');
        $sql->join('manager_relationship', "LEFT JOIN civicrm_relationship AS manager_relationship ON ccc.contact_id = manager_relationship.contact_id_a AND manager_relationship.is_active AND ($managerTypeClause) AND manager_relationship.case_id = a.id");
        $sql->join('manager', 'LEFT JOIN civicrm_contact AS manager ON manager_relationship.contact_id_b = manager.id AND manager.is_deleted <> 1');
        $sql->orderBy("manager.$sortField $dir", NULL, $index);
        $sortString = '(1)';
      }
      // Sort by my role
      elseif (strpos($sortString, 'my_role') === 0) {
        $me = CRM_Core_Session::getLoggedInContactID();
        // Validate inputs
        if (!array_key_exists($sortField, CRM_Contact_DAO_RelationshipType::fieldKeys()) || ($dir != 'ASC' && $dir != 'DESC')) {
          throw new API_Exception("Unknown field specified for sort. Cannot order by '$sortString'");
        }
        $sql->join('ccc', 'LEFT JOIN (SELECT * FROM civicrm_case_contact WHERE id IN (SELECT MIN(id) FROM civicrm_case_contact GROUP BY case_id)) AS ccc ON ccc.case_id = a.id');
        $sql->join('my_relationship', "LEFT JOIN civicrm_relationship AS my_relationship ON ccc.contact_id = my_relationship.contact_id_a AND my_relationship.is_active AND my_relationship.contact_id_b = $me AND my_relationship.case_id = a.id");
        $sql->join('my_relationship_type', 'LEFT JOIN civicrm_relationship_type AS my_relationship_type ON my_relationship_type.id = my_relationship.relationship_type_id');
        $sql->orderBy("my_relationship_type.$sortField $dir", NULL, $index);
        $sortString = '(1)';
      }
    }
    // Remove our extra sort params so the basic_get function doesn't see them
    $params['options']['sort'] = implode(', ', $sort);
    unset($params['option_sort'], $params['option.sort'], $params['sort']);
  }

  return $sql;
}
