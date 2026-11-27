<?php

use Civi\API\Event\AuthorizeEvent;
use CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAccess as Access;

/**
 * Tests the write gate for repeatable Case custom records.
 *
 * Denial for a real repeatable Case group is exercised by the Playwright E2E,
 * since creating a custom group in a transactional headless test would leak
 * its DDL table.
 *
 * @group headless
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAccessTest extends BaseHeadlessTest {

  /**
   * Access all custom data grants edit access to every group.
   */
  public function testManagePermissionIsAccessAllCustomData() {
    $this->assertEquals('access all custom data', Access::MANAGE_PERMISSION);
  }

  /**
   * APIv4: leaves entities other than gated Case groups alone.
   */
  public function testAuthorizeApiV4IgnoresOtherEntities() {
    foreach (['Contact', 'Custom_NotACaseGroup'] as $entity) {
      $event = $this->api4AuthorizeEvent($entity, 'update', TRUE);

      Access::authorizeApi($event);

      $this->assertNull($event->isAuthorized());
      $this->assertFalse($event->isPropagationStopped());
    }
  }

  /**
   * APIv4: reads and checkPermissions => FALSE calls are left alone.
   */
  public function testAuthorizeApiV4IgnoresReadsAndUncheckedCalls() {
    $events = [
      $this->api4AuthorizeEvent('Custom_Publications', 'get', TRUE),
      $this->api4AuthorizeEvent('Custom_Publications', 'create', FALSE),
    ];
    foreach ($events as $event) {
      Access::authorizeApi($event);

      $this->assertNull($event->isAuthorized());
      $this->assertFalse($event->isPropagationStopped());
    }
  }

  /**
   * Only multi-record groups extending Case (or a case category) are gated.
   *
   * Display style and active state do not matter: a disabled group or one not
   * shown as "Tab with table" is still gated.
   */
  public function testIsGatedGroupDependsOnMultipleAndCaseExtends() {
    $this->assertTrue(Access::isGatedGroup(['is_multiple' => 1, 'extends' => 'Case']));
    $this->assertTrue(Access::isGatedGroup([
      'is_multiple' => 1,
      'extends' => 'Case',
      'is_active' => 0,
      'style' => 'Inline',
    ]));
    $this->assertFalse(Access::isGatedGroup(['is_multiple' => 0, 'extends' => 'Case']));
    $this->assertFalse(Access::isGatedGroup(['is_multiple' => 1, 'extends' => 'Individual']));
    $this->assertFalse(Access::isGatedGroup(NULL));
  }

  /**
   * Keys that are not custom fields, or target unknown groups, are ignored.
   */
  public function testGatedCustomParamGroupsIgnoresNonGatedKeys() {
    $params = [
      'id' => 1,
      'subject' => 'x',
      'custom_999999' => 'x',
      'custom_999999_-1' => 'x',
      'custom_NotACaseGroup:some_field' => 'x',
    ];

    $this->assertEquals([], Access::getGatedCustomParamGroups($params));
    $this->assertEquals([], Access::getUnmanageableCustomParamKeys($params));
  }

  /**
   * APIv3: calls that skip permission checks are left alone.
   */
  public function testAuthorizeApiV3RespectsCheckPermissionsFalse() {
    $event = $this->api3AuthorizeEvent('CustomValue', 'create', ['custom_1' => 'x']);

    Access::authorizeApi($event);

    $this->assertNull($event->isAuthorized());
    $this->assertFalse($event->isPropagationStopped());
  }

  /**
   * APIv3: reads are never gated.
   */
  public function testAuthorizeApiV3IgnoresGetActions() {
    $event = $this->api3AuthorizeEvent('CustomValue', 'get', ['check_permissions' => 1, 'custom_1' => 'x']);

    Access::authorizeApi($event);

    $this->assertNull($event->isAuthorized());
  }

  /**
   * Builds a civi.api.authorize event for an APIv4 request.
   *
   * @param string $entity
   *   API entity.
   * @param string $action
   *   API action.
   * @param bool $checkPermissions
   *   Whether the request checks permissions.
   *
   * @return \Civi\API\Event\AuthorizeEvent
   *   The event (not yet authorized either way).
   */
  private function api4AuthorizeEvent(string $entity, string $action, bool $checkPermissions): AuthorizeEvent {
    // A stand-in for an APIv4 action: the listener only reads the version via
    // array access and the entity, action and checkPermissions getters.
    // AbstractAction cannot be mocked for this, as its getters (e.g.
    // getCheckPermissions) are magic (__call) methods.
    $request = new class($entity, $action, $checkPermissions) implements ArrayAccess {

      /**
       * Request values readable via array access.
       *
       * @var array
       */
      private $values;

      /**
       * Whether the request checks permissions.
       *
       * @var bool
       */
      private $checkPermissions;

      /**
       * Constructor.
       *
       * @param string $entity
       *   API entity.
       * @param string $action
       *   API action.
       * @param bool $checkPermissions
       *   Whether the request checks permissions.
       */
      public function __construct(string $entity, string $action, bool $checkPermissions) {
        $this->values = ['version' => 4, 'entity' => $entity, 'action' => $action];
        $this->checkPermissions = $checkPermissions;
      }

      /**
       * Returns the API entity.
       *
       * @return string
       *   API entity.
       */
      public function getEntityName() {
        return $this->values['entity'];
      }

      /**
       * Returns the API action.
       *
       * @return string
       *   API action.
       */
      public function getActionName() {
        return $this->values['action'];
      }

      /**
       * Returns whether the request checks permissions.
       *
       * @return bool
       *   TRUE when permissions are checked.
       */
      public function getCheckPermissions() {
        return $this->checkPermissions;
      }

      /**
       * {@inheritdoc}
       */
      #[\ReturnTypeWillChange]
      public function offsetExists($offset) {
        return isset($this->values[$offset]);
      }

      /**
       * {@inheritdoc}
       */
      #[\ReturnTypeWillChange]
      public function offsetGet($offset) {
        return $this->values[$offset] ?? NULL;
      }

      /**
       * {@inheritdoc}
       */
      #[\ReturnTypeWillChange]
      public function offsetSet($offset, $value) {
      }

      /**
       * {@inheritdoc}
       */
      #[\ReturnTypeWillChange]
      public function offsetUnset($offset) {
      }

    };

    return new AuthorizeEvent(NULL, $request, NULL, 0);
  }

  /**
   * Builds a civi.api.authorize event for an APIv3 request.
   *
   * @param string $entity
   *   API entity.
   * @param string $action
   *   API action.
   * @param array $params
   *   API params.
   *
   * @return \Civi\API\Event\AuthorizeEvent
   *   The event (not yet authorized either way).
   */
  private function api3AuthorizeEvent(string $entity, string $action, array $params): AuthorizeEvent {
    $request = [
      'version' => 3,
      'entity' => $entity,
      'action' => $action,
      'params' => $params,
    ];

    return new AuthorizeEvent(NULL, $request, NULL, 0);
  }

}
