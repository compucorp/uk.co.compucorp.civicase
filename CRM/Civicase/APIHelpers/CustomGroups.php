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
   * Returns NULL if no group was found.
   *
   * @param string $customGroupName
   *   A custom group name.
   *
   * @return int|null
   *   A custom group id or NULL.
   */
  public static function getIdForGroupName($customGroupName) {
    try {
      $result = civicrm_api3('CustomGroup', 'getsingle', [
        'return' => ['id'],
        'name' => $customGroupName,
      ]);

      return !empty($result['id']) ? $result['id'] : NULL;
    }
    catch (CiviCRM_API3_Exception $e) {
    }

    return NULL;
  }

}
