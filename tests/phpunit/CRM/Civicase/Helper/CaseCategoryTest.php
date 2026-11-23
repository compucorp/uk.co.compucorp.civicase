<?php

use CRM_Civicase_Helper_CaseCategory as CaseCategoryHelper;
use CRM_Civicase_Service_CaseCategoryPermission as CaseCategoryPermission;
use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * Tests CRM_Civicase_Helper_CaseCategory.
 *
 * @group headless
 */
class CRM_Civicase_Helper_CaseCategoryTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Fabricated case category.
   *
   * @var array
   */
  private $caseCategory;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->caseCategory = CaseCategoryFabricator::fabricate();
    CRM_Core_PseudoConstant::flush();
    $this->registerCurrentLoggedInContactInSession(ContactFabricator::fabricate()['id']);
  }

  /**
   * {@inheritDoc}
   */
  public function tearDown(): void {
    $this->resetPermissions();
    $this->unregisterCurrentLoggedInContactFromSession();
    parent::tearDown();
  }

  /**
   * A category is accessible with its "basic <category> information".
   */
  public function testCategoryIsAccessibleWithItsBasicInformationPermission() {
    $this->setPermissions([
      'access CiviCRM',
      $this->getCategoryPermission('basic case information'),
    ]);

    $this->assertTrue(
      CaseCategoryHelper::isCaseTypeCategoryAccessible((int) $this->caseCategory['value'])
    );
  }

  /**
   * Other permissions of the category don't make it accessible.
   */
  public function testCategoryIsNotAccessibleWithoutItsBasicInformationPermission() {
    $this->setPermissions([
      'access CiviCRM',
      'basic case information',
      $this->getCategoryPermission('access all cases and activities'),
    ]);

    $this->assertFalse(
      CaseCategoryHelper::isCaseTypeCategoryAccessible((int) $this->caseCategory['value'])
    );
  }

  /**
   * A category that doesn't exist is not accessible.
   */
  public function testUnknownCategoryIsNotAccessible() {
    $this->assertFalse(CaseCategoryHelper::isCaseTypeCategoryAccessible(999999));
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
