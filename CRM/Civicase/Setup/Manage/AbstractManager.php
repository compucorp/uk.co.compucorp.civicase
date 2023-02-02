<?php

/**
 * Interface to manage entities.
 *
 * Describes the interface for entities that are
 * to be managed (created, removed ..etc) during the
 * extension installations, disabling ..etc.
 */
abstract class CRM_Civicase_Setup_Manage_AbstractManager {

  /**
   * Creates the entity.
   */
  abstract public function create(): void;

  /**
   * Removes the entity.
   */
  abstract public function remove(): void;

  /**
   * Disables the entity.
   */
  public function disable(): void {
    $this->toggle(FALSE);
  }

  /**
   * Enables the entity.
   */
  public function enable() {
    $this->toggle(TRUE);
  }

  /**
   * Enables/Disables the entity based on the passed status.
   *
   * @params boolean $status
   *  True to enable the entity, False to disable the entity.
   */
  abstract protected function toggle($status): void;

}
