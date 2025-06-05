<?php

/**
 * CRM_Civicase_Api_Wrapper_TagFilter.
 *
 * Filters out the non-selectable tags when the api request
 * is for case, activity or file related tags.
 */
class CRM_Civicase_Api_Wrapper_TagFilter implements API_Wrapper {

  /**
   * {@inheritdoc}
   */
  public function fromApiInput($apiRequest) {

    // Only add the 'is_selectable' filter if it is not already added.
    if (isset($apiRequest['params']['is_selectable'])) {
      return $apiRequest;
    }

    $usedFor = isset($apiRequest['params']['used_for']['LIKE'])
      ? str_replace("%", "", $apiRequest['params']['used_for']['LIKE']) : '';
    $tagFilteringEntities = ['civicrm_case', 'civicrm_activity', 'civicrm_file'];

    // For regular tags we have a param 'used_for', set in api request.
    if (in_array($usedFor, $tagFilteringEntities, TRUE)) {
      $apiRequest['params']['is_selectable'] = 1;
    }

    // For non-regular tags we have a param 'parent_id' set in api request.
    if (isset($apiRequest['params']['parent_id']) && ((int) $apiRequest['params']['parent_id']) > 0) {
      $tagData = civicrm_api4('Tag', 'get', [
        'select' => ['used_for'],
        'where' => [['id', '=', $apiRequest['params']['parent_id']]],
      ])->getArrayCopy()[0] ?? [];

      if (isset($tagData['used_for']) && !empty(array_intersect($tagData['used_for'], $tagFilteringEntities))) {
        $apiRequest['params']['is_selectable'] = 1;
      }
    }

    return $apiRequest;
  }

  /**
   * {@inheritdoc}
   */
  public function toApiOutput($apiRequest, $result) {
    // No changes to output.
    return $result;
  }

}
