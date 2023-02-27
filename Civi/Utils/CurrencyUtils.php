<?php

namespace Civi\Utils;

use CRM_Core_DAO;

/**
 * Utility class to manage CiviCRM currency table.
 */
class CurrencyUtils {

  /**
   * CiviCRM Currencies.
   *
   * @var array
   */
  private static $currencies;

  /**
   * Returns a list of currencies supported by CiviCRM.
   *
   * @return array
   *   array reference of all currency names and symbols
   */
  public static function getCurrencies() {
    if (!self::$currencies) {
      $query = "SELECT name, symbol FROM civicrm_currency";
      $dao = CRM_Core_DAO::executeQuery($query);
      self::$currencies = [];
      while ($dao->fetch()) {
        self::$currencies[] = ['name' => $dao->name, 'symbol' => $dao->symbol];
      }
    }

    return self::$currencies;
  }

}
