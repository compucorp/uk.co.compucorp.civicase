<?php

namespace Civi\Api4;

use Civi\Api4\Generic\DAOEntity;
use Civi\Api4\Action\CaseSalesOrder\ComputeTotalAction;
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

  /**
   * Compute the sum of the line items value.
   *
   * @param bool $checkPermissions
   *   Should permission be checked for the user.
   *
   * @return Civi\Api4\Action\CaseSalesOrder\SalesOrderSaveAction
   *   returns save order action
   */
  public static function computeTotal($checkPermissions = FALSE) {
    return (new ComputeTotalAction(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

}
