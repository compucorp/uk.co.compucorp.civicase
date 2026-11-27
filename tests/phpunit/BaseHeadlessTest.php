<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;

/**
 * Base test class.
 */
abstract class BaseHeadlessTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * Applies the headless site before the class creates shared fixtures.
   *
   * CiviTestListener applies a test's headless site when each test starts,
   * which is after setUpBeforeClass(). When the previous test class used a
   * different site (e.g. one with SearchKit), that first apply rebuilds the
   * database and removes whatever setUpBeforeClass() created. Subclasses that
   * create fixtures in setUpBeforeClass() must call this first.
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    (new static())->setUpHeadless();
  }

  /**
   * {@inheritDoc}
   */
  public function setUpHeadless() {
    return Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

}
