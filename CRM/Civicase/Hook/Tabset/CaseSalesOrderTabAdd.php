<?php

use Civi\Api4\CaseSalesOrder;
use CRM_Civicase_Service_CaseTypeCategoryFeatures as CaseTypeCategoryFeatures;

/**
 * Hook Class to add case sales order tab to contact.
 */
class CRM_Civicase_Hook_Tabset_CaseSalesOrderTabAdd {

  /**
   * Add Case Sales Order Tab.
   *
   * @param array $tabs
   *   Tabs list.
   * @param int $contactID
   *   Contact ID.
   * @param bool $weight
   *   Weight to position the tab.
   */
  public function addCaseSalesOrderTab(array &$tabs, $contactID, $weight) {
    $caseTypeCategoryFeatures = new CaseTypeCategoryFeatures();
    $caseInstances = $caseTypeCategoryFeatures->retrieveCaseInstanceWithEnabledFeatures(['quotations']);

    if (empty($caseInstances)) {
      return;
    }

    $tabs[] = [
      'id' => 'quotations',
      'url' => CRM_Utils_System::url("civicrm/case-features/quotations/contact-tab?cid=$contactID"),
      'title' => 'Quotations',
      'weight' => $weight,
      'count' => $this->getContactSalesOrderCount($contactID),
      'icon' => '',
    ];
  }

  /**
   * Returns the number of sales order owned by a contact.
   *
   * @param int $contactID
   *   Contact ID to retrieve count for.
   */
  public function getContactSalesOrderCount(int $contactID) {
    $result = CaseSalesOrder::get()
      ->addSelect('COUNT(id) AS count')
      ->addWhere('client_id', '=', $contactID)
      ->execute()
      ->jsonSerialize();

    return $result[0]['count'];
  }

}
