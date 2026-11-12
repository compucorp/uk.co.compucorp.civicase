<?php

use Civi\Core\Event\GenericHookEvent;
use Civi\Test;
use Civi\Test\HeadlessInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the custom fields offered as filters on the case list.
 *
 * @group headless
 */
class CRM_Civicase_SettingsCustomSearchFieldsTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * A Case group the ACL lets the user view.
   *
   * @var array
   */
  private $viewableGroup;

  /**
   * A Case group the user may not view.
   *
   * @var array
   */
  private $hiddenGroup;

  /**
   * The ACL listener granting the viewable group.
   *
   * @var callable
   */
  private $aclListener;

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
   * Creates the two groups and grants the user the first one through an ACL.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
    $this->viewableGroup = $this->createCustomGroup(
      ['title' => 'Viewable', 'extends' => 'Case', 'style' => 'Inline'],
      ['note' => ['is_searchable' => 1]]
    );
    $this->hiddenGroup = $this->createCustomGroup(
      ['title' => 'Hidden', 'extends' => 'Case', 'style' => 'Inline'],
      ['note' => ['is_searchable' => 1]]
    );

    $viewableGroupId = (int) $this->viewableGroup['id'];
    $this->aclListener = function (GenericHookEvent $event) use ($viewableGroupId) {
      if ($event->tableName === 'civicrm_custom_group') {
        $event->currentGroups[] = $viewableGroupId;
      }
    };
    Civi::dispatcher()->addListener('hook_civicrm_aclGroup', $this->aclListener);
  }

  /**
   * Removes the ACL listener, the permissions and the test data.
   */
  public function tearDown(): void {
    Civi::dispatcher()->removeListener('hook_civicrm_aclGroup', $this->aclListener);
    $this->resetPermissions();
    $this->cleanUpRepeatableCaseCustomData();
  }

  /**
   * Without "access all custom data", only ACL-granted groups are offered.
   */
  public function testOnlyGroupsTheUserCanViewAreOffered() {
    $this->setPermissions(['access CiviCRM']);

    $groupIds = $this->offeredCaseGroupIds();

    $this->assertContains((int) $this->viewableGroup['id'], $groupIds);
    $this->assertNotContains((int) $this->hiddenGroup['id'], $groupIds);
  }

  /**
   * With "access all custom data", every group is offered.
   */
  public function testAllGroupsAreOfferedWithAccessToAllCustomData() {
    $this->setPermissions(['access CiviCRM', 'access all custom data']);

    $groupIds = $this->offeredCaseGroupIds();

    $this->assertContains((int) $this->viewableGroup['id'], $groupIds);
    $this->assertContains((int) $this->hiddenGroup['id'], $groupIds);
  }

  /**
   * The ids of the Case custom groups offered as case list filters.
   *
   * @return int[]
   *   Custom group ids.
   */
  private function offeredCaseGroupIds(): array {
    $options = ['caseTypes' => []];
    CRM_Civicase_Settings::setCustomFieldsInfoToJsVars($options);

    return array_map('intval', array_column($options['customSearchFields'], 'id'));
  }

}
