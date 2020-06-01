<?php

/**
 * Custom Groups API Helper Class.
 */
class CRM_Civicase_APIHelpers_CustomGroups {

  /**
   * Returns a list of all active custom groups.
   *
   * @return array
   *   Custom Group Api response.
   */
  public static function getAllActiveGroups() {
    return civicrm_api3('CustomGroup', 'get', [
      'extends' => $params['entity_type'],
      'options' => $params['options'],
      'is_active' => 1,
    ]);
  }

  /**
   * Returns the custom group ID for the given custom group name.
   *
   * Does not support multiple custom group names. Returns NULL if no group
   * was found.
   *
   * @return int|null
   *   A custom group id or NULL.
   */
  public static function getGroupIdFromSingleGroupName($params) {
    if (!empty($params['custom_group.name']) && !is_array($params['custom_group.name'])) {
      try {
        $result = civicrm_api3('CustomGroup', 'getsingle', [
          'return' => ['id'],
          'name' => $params['custom_group.name'],
        ]);

        return !empty($result['id']) ? $result['id'] : NULL;
      }
      catch (CiviCRM_API3_Exception $e) {
      }
    }

    return NULL;
  }

}
