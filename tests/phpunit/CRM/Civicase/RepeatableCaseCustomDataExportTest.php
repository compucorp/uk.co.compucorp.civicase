<?php

use Civi\Core\Event\GenericHookEvent;
use Civi\Test;
use Civi\Test\HeadlessInterface;
use PHPUnit\Framework\TestCase;

/**
 * SearchKit exports of repeatable Case custom data respect permissions.
 *
 * @group headless
 */
class CRM_Civicase_RepeatableCaseCustomDataExportTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * Name of the saved search.
   */
  const SEARCH = 'civicase_test_repeatable_case_export';

  /**
   * Name of the search's table display.
   */
  const DISPLAY = 'civicase_test_repeatable_case_export_table';

  /**
   * Repeatable Case group with a Title field.
   *
   * @var array
   */
  private $group;

  /**
   * Case with two records in the group.
   *
   * @var int
   */
  private $caseId;

  /**
   * Custom groups that an ACL lets the user view.
   *
   * @var int[]
   */
  private $viewableGroupIds = [];

  /**
   * {@inheritdoc}
   */
  public function setUpHeadless() {
    return Test::headless()
      ->install(['org.civicrm.search_kit'])
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * Creates the group, a Case with two records and the saved search.
   */
  public function setUp(): void {
    parent::setUp();
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->group = $this->createRepeatableCaseGroup('Publications', [
      'title' => ['label' => 'Title'],
    ]);
    $this->caseId = $this->createCase();
    $this->createRecord($this->group, $this->caseId, ['title' => 'First']);
    $this->createRecord($this->group, $this->caseId, ['title' => 'Second']);
    $this->createExportSearch();
    Civi::dispatcher()->addListener('hook_civicrm_aclGroup', [$this, 'grantViewAccess']);
  }

  /**
   * Removes the saved search and the fixture.
   */
  public function tearDown(): void {
    Civi::dispatcher()->removeListener('hook_civicrm_aclGroup', [$this, 'grantViewAccess']);
    // Deleting the saved search also deletes its display.
    civicrm_api4('SavedSearch', 'delete', [
      'checkPermissions' => FALSE,
      'where' => [['name', '=', self::SEARCH]],
    ]);
    $this->resetPermissions();
    $this->cleanUpRepeatableCaseCustomData();
    parent::tearDown();
  }

  /**
   * Listener for `hook_civicrm_aclGroup`: an ACL granting View on groups.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   The aclGroup hook event.
   */
  public function grantViewAccess(GenericHookEvent $event): void {
    if ($event->tableName === 'civicrm_custom_group' && (int) $event->type === CRM_Core_Permission::VIEW) {
      $event->currentGroups = array_values(array_unique(array_merge(
        (array) $event->currentGroups,
        $this->viewableGroupIds
      )));
    }
  }

  /**
   * A user who may see the Case and all custom data gets every record.
   */
  public function testUserWithFullAccessExportsEveryRecord() {
    $this->logInAs([
      'access CiviCRM',
      'view all contacts',
      'access all cases and activities',
      'access all custom data',
    ]);

    $this->assertEquals([
      [$this->caseId, 'First'],
      [$this->caseId, 'Second'],
    ], $this->download());
  }

  /**
   * Cases the user may not see, and their records, are left out.
   */
  public function testCasesTheUserCannotAccessAreNotExported() {
    $this->logInAs([
      'access CiviCRM',
      'view all contacts',
      'access my cases and activities',
      'access all custom data',
    ]);

    $this->assertEquals([], $this->download());
  }

  /**
   * Without View access to the group, its values are blank.
   */
  public function testGroupWithoutViewAccessExportsBlankValues() {
    $this->logInAs([
      'access CiviCRM',
      'view all contacts',
      'access all cases and activities',
    ]);

    $this->assertEquals([[$this->caseId, NULL]], $this->download());
  }

  /**
   * An ACL granting View on the group exports its records.
   */
  public function testViewAccessThroughAclExportsRecords() {
    $this->viewableGroupIds = [(int) $this->group['id']];
    $this->logInAs([
      'access CiviCRM',
      'view all contacts',
      'access all cases and activities',
    ]);

    $this->assertEquals([
      [$this->caseId, 'First'],
      [$this->caseId, 'Second'],
    ], $this->download());
  }

  /**
   * Logs in as a new contact with the given permissions.
   *
   * A new contact has no cases of its own, and no ACL results cached.
   *
   * @param string[] $permissions
   *   Permissions of the user.
   */
  private function logInAs(array $permissions): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->setPermissions($permissions);
  }

  /**
   * Saves a Case search joined to the group, with a downloadable table.
   *
   * The join alias is the one the SearchKit UI gives the first join.
   */
  private function createExportSearch(): void {
    $join = 'Case_Custom_' . $this->group['name'] . '_entity_id_01';
    $search = civicrm_api4('SavedSearch', 'create', [
      'checkPermissions' => FALSE,
      'values' => [
        'name' => self::SEARCH,
        'label' => 'Repeatable Case export',
        'api_entity' => 'Case',
        'api_params' => [
          'version' => 4,
          'select' => ['id', "$join.title"],
          'join' => [
            ["Custom_{$this->group['name']} AS $join", 'LEFT', ['id', '=', "$join.entity_id"]],
          ],
          'where' => [['id', '=', $this->caseId]],
        ],
      ],
    ])->first();
    civicrm_api4('SearchDisplay', 'create', [
      'checkPermissions' => FALSE,
      'values' => [
        'saved_search_id' => $search['id'],
        'name' => self::DISPLAY,
        'label' => 'Repeatable Case export',
        'type' => 'table',
        'settings' => [
          'actions' => TRUE,
          'columns' => [
            ['type' => 'field', 'key' => 'id', 'label' => 'Case ID'],
            ['type' => 'field', 'key' => "$join.title", 'label' => 'Publications: Title'],
          ],
          // Not sorted by the group's fields: a user without access to the
          // group may not sort by them.
          'sort' => [['id', 'ASC']],
        ],
      ],
    ]);
  }

  /**
   * Downloads the display as the logged-in user.
   *
   * Uses Download Spreadsheet's "array" format: the header, then the rows.
   *
   * @return array
   *   The exported rows, without the header, ordered by title.
   */
  private function download(): array {
    $result = (array) civicrm_api4('SearchDisplay', 'download', [
      'savedSearch' => self::SEARCH,
      'display' => self::DISPLAY,
      'format' => 'array',
    ]);
    $this->assertEquals(['Case ID', 'Publications: Title'], $result[0] ?? NULL);
    $rows = array_slice($result, 1);
    usort($rows, fn ($a, $b) => strcmp((string) $a[1], (string) $b[1]));

    return $rows;
  }

}
