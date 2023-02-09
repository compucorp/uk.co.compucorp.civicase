<?php

use PHPUnit\Framework\TestCase;
use Civi\Test;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * Base test class.
 */
abstract class BaseHeadlessTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * {@inheritDoc}
   */
  public function setUpHeadless() {
    return Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * {@inheritDoc}
   */
  public function getMockBuilder($className) {
    $mockBuilder = (new class($this, $className) extends PHPUnit_Framework_MockObject_MockBuilder {

      /**
       * {@inheritDoc}
       */
      public function getMock() {
        static::setSupressedErrorHandler();

        try {
          return parent::getMock();
        } finally {
          restore_error_handler();
        }
      }

      /**
       * Supress depreciation warnings.
       */
      public static function setSupressedErrorHandler() {
        $previousHandler = set_error_handler(function ($code, $description, $file = NULL, $line = NULL, $context = NULL) use (&$previousHandler) {
          if ($code & E_DEPRECATED) {
              return TRUE;
          }

            return $previousHandler($code, $description, $file, $line, $context);
        });
      }

    });

    return $mockBuilder;
  }

}
