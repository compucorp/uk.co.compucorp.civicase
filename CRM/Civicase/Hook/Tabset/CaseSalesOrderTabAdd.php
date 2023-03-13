<?php

use Civi\Api4\CaseSalesOrder;

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
