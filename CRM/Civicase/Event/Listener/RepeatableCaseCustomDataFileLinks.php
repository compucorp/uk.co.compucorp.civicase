<?php

use Civi\API\Event\PrepareEvent;
use Civi\Api4\Action\SearchDisplay\Download;
use Civi\Api4\Action\SearchDisplay\Run;
use CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataDownload as RecordsDownload;
use CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataRun as RecordsRun;
use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Links the files in the records table of a repeatable Case group.
 *
 * The table and its download both link each file through the record's Case
 * (see CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataFileLinksTrait),
 * and the download exports the link instead of the file name.
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataFileLinks {

  /**
   * Listener for `civi.api.prepare`: swaps in civicase's run and download.
   *
   * @param \Civi\API\Event\PrepareEvent $event
   *   The API prepare event.
   */
  public static function useFileLinkActions(PrepareEvent $event): void {
    $request = $event->getApiRequest();
    if ($request instanceof Download) {
      $action = RecordsDownload::class;
    }
    elseif ($request instanceof Run) {
      $action = RecordsRun::class;
    }
    else {
      return;
    }
    if ($request instanceof $action) {
      return;
    }
    $savedSearch = $request->getSavedSearch();
    if (is_string($savedSearch) && self::isRecordsSearch($savedSearch)) {
      $event->setApiRequest($action::copyOf($request));
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
