<?php

namespace Civi\Api4;

use Civi\Api4\Action\CaseSalesOrder\ComputeTotalAction;
use Civi\Api4\Action\CaseSalesOrder\ComputeTotalAmountInvoicedAction;
use Civi\Api4\Action\CaseSalesOrder\ComputeTotalAmountPaidAction;
use Civi\Api4\Action\CaseSalesOrder\SalesOrderSaveAction;
use Civi\Api4\Generic\DAOEntity;

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
   * @return Civi\Api4\Action\CaseSalesOrder\ComputeTotalAction
   *   returns computed total action
   */
  public static function computeTotal($checkPermissions = FALSE) {
    return (new ComputeTotalAction(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * Computes the sum of amount paid.
   *
   * @param bool $checkPermissions
   *   Should permission be checked for the user.
   *
   * @return ComputeAmountPaidAction
   *   returns computed amount paid action
   */
  public static function computeTotalAmountPaid(bool $checkPermissions = FALSE) {
    return (new ComputeTotalAmountPaidAction(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * Computes the sum of amount invoiced.
   *
   * @param bool $checkPermissions
   *   Should permission be checked for the user.
   *
   * @return ComputeAmountInvoicedAction
   *   returns computed amount invoiced action
   */
  public static function computeTotalAmountInvoiced(bool $checkPermissions = FALSE) {
    return (new ComputeTotalAmountInvoicedAction(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

}
