<?php

use Civi\Core\Event\GenericHookEvent;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * Tests CRM_Civicase_Event_Listener_CivicaseAngularResources.
 *
 * @group headless
 */
class CRM_Civicase_Event_Listener_CivicaseAngularResourcesTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Angular modules the page loader had before the test.
   *
   * @var array
   */
  private $originalModules;

  /**
   * The logged in contact's ID.
   *
   * @var int
   */
  private $contactId;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->contactId = (int) ContactFabricator::fabricate()['id'];
    $this->registerCurrentLoggedInContactInSession($this->contactId);

    $loader = Civi::service('angularjs.loader');
    $this->originalModules = $loader->getModules();
    $loader->setModules([]);
    unset(Civi::$statics['CRM_Core_Region']);
  }

  /**
   * {@inheritDoc}
   */
  public function tearDown(): void {
    Civi::service('angularjs.loader')->setModules($this->originalModules);
    unset(Civi::$statics['CRM_Core_Region']);
    $this->unregisterCurrentLoggedInContactFromSession();
    parent::tearDown();
  }

  /**
   * A page with a module that requires "civicase" gets its settings.
   */
  public function testAddsConfigSettingsWhenPageModuleRequiresCivicase() {
    Civi::service('angularjs.loader')->addModules(['my-activities']);

    $this->renderRegion('html-header');

    $config = $this->getPageConfigSettings();
    $this->assertSame($this->contactId, $config['user_contact_id'] ?? NULL);
    $this->assertSame(
      CRM_Core_Config::singleton()->enableComponents,
      $config['enableComponents'] ?? NULL
    );
  }

  /**
   * Pages without the "civicase" module get nothing.
   */
  public function testAddsNothingWhenNoPageModuleRequiresCivicase() {
    Civi::service('angularjs.loader')->addModules(['crmUi']);

    $this->renderRegion('html-header');

    $this->assertArrayNotHasKey('user_contact_id', $this->getPageConfigSettings());
  }

  /**
   * Only the page header gets them.
   */
  public function testAddsNothingWhenAnotherRegionRenders() {
    Civi::service('angularjs.loader')->addModules(['civicase']);

    $this->renderRegion('page-footer');

    $this->assertArrayNotHasKey('user_contact_id', $this->getPageConfigSettings());
  }

  /**
   * Dispatches the event core sends before rendering a region.
   *
   * @param string $regionName
   *   Name of the region.
   */
  private function renderRegion($regionName) {
    Civi::dispatcher()->dispatch(
      'civi.region.render',
      GenericHookEvent::create(['region' => CRM_Core_Region::instance($regionName)])
    );
  }

  /**
   * Returns the CRM.config settings added to the page header.
   *
   * @return array
   *   The settings, without the ones from settings factories.
   */
  private function getPageConfigSettings() {
    $settings = CRM_Core_Region::instance('html-header')->get('settings')['settings'] ?? [];

    return $settings['config'] ?? [];
  }

}
