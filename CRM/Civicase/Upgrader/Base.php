<?php

// This file provides a compatibility shim for upgrade Step classes that call
// CRM_Civicase_Upgrader_Base::instance(). The main upgrader (CRM_Civicase_Upgrader)
// extends CRM_Extension_Upgrader_Base directly; this class exists solely to satisfy
// the static instance() + executeSqlFile() calls in the Steps.

use CRM_Civicase_ExtensionUtil as E;

/**
 * Compatibility base for CiviCase upgrade step classes.
 */
class CRM_Civicase_Upgrader_Base {

  /**
   * @var static
   */
  protected static $instance;

  /**
   * Returns the singleton upgrader instance.
   *
   * @return static
   */
  public static function instance() {
    if (!isset(self::$instance)) {
      self::$instance = new static();
    }
    return self::$instance;
  }

  /**
   * Execute a SQL file relative to the extension root.
   *
   * @param string $relativePath
   *
   * @return bool
   */
  public function executeSqlFile($relativePath) {
    CRM_Utils_File::sourceSQLFile(
      CIVICRM_DSN,
      E::path() . DIRECTORY_SEPARATOR . $relativePath
    );
    return TRUE;
  }

}
