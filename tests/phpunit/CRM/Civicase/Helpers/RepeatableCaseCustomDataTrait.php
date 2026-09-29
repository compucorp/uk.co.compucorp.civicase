<?php

use CRM_Civicase_Test_Fabricator_Case as CaseFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * Real custom groups, Cases and contacts for the importer tests (TCOSB-64).
 *
 * Creating a custom group or field runs DDL, which ends any open database
 * transaction, so tests using this trait must NOT be transactional. Instead,
 * everything created here is tracked and removed again by
 * cleanUpRepeatableCaseCustomData(), which the test's tearDown() must call.
 *
 * Saving a repeatable Case group provisions its SearchKit tab (TCOSB-51), so
 * the test's headless site needs SearchKit installed.
 */
trait CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait {

  /**
   * Ids of the custom groups created by the test.
   *
   * @var int[]
   */
  private $createdCustomGroupIds = [];

  /**
   * Ids of the Cases created by the test.
   *
   * @var int[]
   */
  private $createdCaseIds = [];

  /**
   * Ids of the contacts created by the test.
   *
   * @var int[]
   */
  private $createdContactIds = [];

  /**
   * Creates an active repeatable ("Tab with table") Case custom group.
   *
   * @param string $title
   *   Group title.
   * @param array $fields
   *   CustomField params keyed by field name. Label defaults to the name,
   *   data type to String and HTML type to Text.
   *
   * @return array
   *   The group: 'id', 'name', 'title', and 'fields' keyed by field name.
   */
  private function createRepeatableCaseGroup(string $title, array $fields): array {
    return $this->createCustomGroup([
      'title' => $title,
      'extends' => 'Case',
      'is_multiple' => 1,
      'style' => 'Tab with table',
    ], $fields);
  }

  /**
   * Creates a custom group and its fields.
   *
   * @param array $groupParams
   *   CustomGroup params; 'title', 'extends' and 'style' are required.
   * @param array $fields
   *   CustomField params keyed by field name, as in
   *   createRepeatableCaseGroup().
   *
   * @return array
   *   The group: 'id', 'name', 'title', and 'fields' keyed by field name.
   */
  private function createCustomGroup(array $groupParams, array $fields): array {
    $group = civicrm_api3('CustomGroup', 'create', $groupParams + [
      'name' => 'test_' . substr(md5(mt_rand()), 0, 12),
      'is_active' => 1,
    ]);
    $group = reset($group['values']);
    $this->createdCustomGroupIds[] = (int) $group['id'];

    $group['fields'] = [];
    foreach ($fields as $name => $field) {
      $created = civicrm_api3('CustomField', 'create', $field + [
        'custom_group_id' => $group['id'],
        'name' => $name,
        'label' => $name,
        'data_type' => 'String',
        'html_type' => 'Text',
        'is_active' => 1,
      ]);
      $group['fields'][$name] = reset($created['values']);
    }

    return $group;
  }

  /**
   * Creates a contact.
   *
   * @return int
   *   Contact id.
   */
  private function createContact(): int {
    $contactId = (int) ContactFabricator::fabricate()['id'];
    $this->createdContactIds[] = $contactId;

    return $contactId;
  }

  /**
   * Creates a Case for a new client, created by the logged-in contact.
   *
   * @return int
   *   Case id.
   */
  private function createCase(): int {
    $clientId = $this->createContact();
    $case = CaseFabricator::fabricate([
      'case_type_id' => CaseTypeFabricator::fabricate()['id'],
      'contact_id' => $clientId,
      'creator_id' => CRM_Core_Session::getLoggedInContactID(),
    ]);
    $this->createdCaseIds[] = (int) $case['id'];

    return (int) $case['id'];
  }

  /**
   * Custom field key as the importer uses it, e.g. custom_12.
   *
   * @param array $group
   *   A group returned by createCustomGroup().
   * @param string $fieldName
   *   Field name within the group.
   *
   * @return string
   *   The importer column key.
   */
  private function fieldKey(array $group, string $fieldName): string {
    return 'custom_' . $group['fields'][$fieldName]['id'];
  }

  /**
   * Creates a record in a repeatable group directly, through APIv4.
   *
   * @param array $group
   *   A group returned by createCustomGroup().
   * @param int $caseId
   *   The Case the record belongs to.
   * @param array $values
   *   Field values keyed by field name.
   *
   * @return int
   *   The record id.
   */
  private function createRecord(array $group, int $caseId, array $values): int {
    return (int) civicrm_api4('Custom_' . $group['name'], 'create', [
      'values' => ['entity_id' => $caseId] + $values,
      'checkPermissions' => FALSE,
    ])->first()['id'];
  }

  /**
   * The records of a repeatable group on a Case, in id order.
   *
   * @param array $group
   *   A group returned by createCustomGroup().
   * @param int $caseId
   *   Case id.
   *
   * @return array
   *   Records, each with 'id', 'entity_id' and the field values by name.
   */
  private function getRecords(array $group, int $caseId): array {
    return civicrm_api4('Custom_' . $group['name'], 'get', [
      'where' => [['entity_id', '=', $caseId]],
      'orderBy' => ['id' => 'ASC'],
      'checkPermissions' => FALSE,
    ])->getArrayCopy();
  }

  /**
   * Removes the custom groups, Cases and contacts the test created.
   *
   * Deleting a group also drops its table, and with it the records.
   */
  private function cleanUpRepeatableCaseCustomData(): void {
    foreach (array_reverse($this->createdCustomGroupIds) as $groupId) {
      civicrm_api3('CustomGroup', 'delete', ['id' => $groupId]);
    }
    foreach ($this->createdCaseIds as $caseId) {
      civicrm_api3('Case', 'delete', ['id' => $caseId, 'move_to_trash' => 0]);
    }
    // CiviCRM refuses to delete the logged-in contact.
    CRM_Core_Session::singleton()->set('userID', NULL);
    foreach ($this->createdContactIds as $contactId) {
      civicrm_api3('Contact', 'delete', [
        'id' => $contactId,
        'skip_undelete' => 1,
      ]);
    }
    $this->createdCustomGroupIds = [];
    $this->createdCaseIds = [];
    $this->createdContactIds = [];
  }

}
