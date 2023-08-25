<?php

/**
 * Manages Membership Type Custom Field Entity.
 */
class CRM_Civicase_Setup_Manage_MembershipTypeCustomFieldManager extends CRM_Civicase_Setup_Manage_AbstractManager {

  const OPTION_GROUP_NAME = 'cg_extend_objects';

  /**
   * Adds Membership Type entity to extend object.
   */
  public function create(): void {
    CRM_Core_BAO_OptionValue::ensureOptionValueExists([
      'option_group_id' => self::OPTION_GROUP_NAME,
      "label" => "Membership Type",
      "value" => "MembershipType",
      "name" => "civicrm_membership_type",
      'is_active' => TRUE,
      'is_reserved' => TRUE,
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function remove(): void {}

  /**
   * {@inheritDoc}
   */
  protected function toggle($status): void {}

}
