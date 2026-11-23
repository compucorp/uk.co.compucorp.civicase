<?php

use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;
use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * Tests the case category access check of the civicrm/case/a page.
 *
 * @group headless
 */
class CRM_Civicase_Page_CaseAngularTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Fabricated case category.
   *
   * @var array
   */
  private $caseCategory;

  /**
   * Angular modules the page loader had before the test.
   *
   * @var array
   */
  private $originalModules;

  /**
   * Angular page name the page loader had before the test.
   *
   * @var string
   */
  private $originalPageName;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->caseCategory = CaseCategoryFabricator::fabricate();
    CRM_Core_PseudoConstant::flush();
    $this->registerCurrentLoggedInContactInSession(ContactFabricator::fabricate()['id']);

    $loader = Civi::service('angularjs.loader');
    $this->originalModules = $loader->getModules();
    $this->originalPageName = $loader->getPageName();
  }

  /**
   * {@inheritDoc}
   */
  public function tearDown(): void {
    unset($_REQUEST['case_type_category']);
    $this->resetPermissions();
    $this->unregisterCurrentLoggedInContactFromSession();

    $loader = Civi::service('angularjs.loader');
    $loader->setModules($this->originalModules);
    $loader->setPageName($this->originalPageName);
    parent::tearDown();
  }

  /**
   * Users without basic access to the URL's category are denied.
   */
  public function testDeniesUserWithoutBasicInformationForTheUrlCategory() {
    $_REQUEST['case_type_category'] = $this->caseCategory['value'];
    $this->setPermissions([
      'access CiviCRM',
      'basic case information',
      'access all cases and activities',
      $this->getCategoryPermission('access all cases and activities'),
    ]);

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('You do not have permission to access this page.');

    $this->runPage();
  }

  /**
   * Users with basic access to the URL's category can open the page.
   */
  public function testAllowsUserWithBasicInformationForTheUrlCategory() {
    $_REQUEST['case_type_category'] = $this->caseCategory['value'];
    $this->setPermissions([
      'access CiviCRM',
      $this->getCategoryPermission('basic case information'),
      $this->getCategoryPermission('access all cases and activities'),
    ]);

    $this->runPage();

    $this->assertContains('civicase', Civi::service('angularjs.loader')->getModules());
  }

  /**
   * Without a category in the URL, the page needs basic access to "Cases".
   */
  public function testDeniesUserWithoutBasicCaseInformationWhenTheUrlHasNoCategory() {
    $this->setPermissions([
      'access CiviCRM',
      'access all cases and activities',
      $this->getCategoryPermission('basic case information'),
    ]);

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('You do not have permission to access this page.');

    $this->runPage();
  }

  /**
   * Without a category in the URL, basic access to "Cases" is enough.
   */
  public function testAllowsUserWithBasicCaseInformationWhenTheUrlHasNoCategory() {
    $this->setPermissions([
      'access CiviCRM',
      'basic case information',
      'access all cases and activities',
    ]);

    $this->runPage();

    $this->assertContains('civicase', Civi::service('angularjs.loader')->getModules());
  }

  /**
   * Runs the page without rendering its template.
   */
  private function runPage() {
    $page = new CRM_Civicase_Page_CaseAngular();
    $page->setEmbedded(TRUE);
    $page->run();
  }

  /**
   * Returns the fabricated category's version of a case permission.
   *
   * @param string $casePermission
   *   A case permission, e.g. "basic case information".
   *
   * @return string
   *   The permission for the fabricated category.
   */
  private function getCategoryPermission($casePermission) {
    return (new CaseCategoryPermission())
      ->replaceWords($casePermission, $this->caseCategory['name']);
  }

}
