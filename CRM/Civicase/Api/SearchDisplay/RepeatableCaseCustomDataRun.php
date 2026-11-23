<?php

use Civi\Api4\Action\SearchDisplay\Run;

/**
 * Runs the records table of a repeatable Case group, linking its files.
 *
 * Used in place of SearchKit's own action by
 * CRM_Civicase_Event_Listener_RepeatableCaseCustomDataFileLinks.
 */
class CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataRun extends Run {

  use CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataFileLinksTrait;

}
