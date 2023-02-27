<?php

namespace Civi\Api4;

use Civi\Api4\Generic\DAOEntity;
use Civi\Api4\Action\CaseSalesOrder\SalesOrderSaveAction;

/**
 * CaseSalesOrder entity.
 *
 * Provided by the CiviCase extension.
 *
 * @package Civi\Api4
 */
class CaseSalesOrder extends DAOEntity {

  /**
   * Creates or Updates a SalesOrder with the line items.
   *
   * @param bool $checkPermissions
   *   Should permission be checked for the user.
   *
   * @return Civi\Api4\Action\CaseSalesOrder\SalesOrderSaveAction
   *   returns save order action
   */
  public static function save($checkPermissions = TRUE) {
    return (new SalesOrderSaveAction(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

}
