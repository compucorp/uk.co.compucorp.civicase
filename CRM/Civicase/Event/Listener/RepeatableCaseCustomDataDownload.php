<?php

use Civi\API\Event\PrepareEvent;
use Civi\Api4\Action\SearchDisplay\Download;
use CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataDownload as FileUrlDownload;
use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Exports file links from the records table of a repeatable Case group.
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataDownload {

  /**
   * Listener for `civi.api.prepare`: swaps in civicase's download action.
   *
   * @param \Civi\API\Event\PrepareEvent $event
   *   The API prepare event.
   */
  public static function useFileUrlDownload(PrepareEvent $event): void {
    $request = $event->getApiRequest();
    if (!$request instanceof Download || $request instanceof FileUrlDownload) {
      return;
    }
    $savedSearch = $request->getSavedSearch();
    if (is_string($savedSearch) && self::isRecordsSearch($savedSearch)) {
      $event->setApiRequest(FileUrlDownload::copyOf($request));
    }
  }

  /**
   * Whether a saved search is the records table of a repeatable Case group.
   *
   * @param string $name
   *   Saved search name.
   *
   * @return bool
   *   TRUE for a repeatable Case group's records table.
   */
  public static function isRecordsSearch(string $name): bool {
    $service = new CaseCustomAfforms();
    foreach ($service->getRepeatableCaseGroups() as $group) {
      if ($name === CaseCustomAfforms::getRecordsSearchName($group)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
