<?php

use CRM_Civicase_Service_RepeatableCaseCustomImporter as Importer;
use CRM_Civicase_Test_Fabricator_Case as CaseFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;
use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;

/**
 * Tests the repeatable Case custom-data importer service (TCOSB-64 / 1.8).
 *
 * A real Case can be created here (plain rows, cleanly rolled back). The
 * happy-path record create needs a real repeatable custom group, whose DDL
 * table would leak this transactional test, so that is exercised by the
 * Playwright E2E (import-repeatable-case-custom-data.spec.ts) — mirroring the
 * approach in RepeatableCaseCustomGroupAfformsTest.
 *
 * @group headless
 */
class CRM_Civicase_Service_RepeatableCaseCustomImporterTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Registers a logged-in contact (needed when fabricating a Case).
   */
  public function setUp() {
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
  }

  /**
   * A row without case_id is rejected.
   */
  public function testImportRowRequiresCaseId() {
    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('case_id is required');
    Importer::importRow(['custom_1' => 'x']);
  }

  /**
   * A row referencing a non-existent Case is rejected.
   */
  public function testImportRowRejectsUnknownCase() {
    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('not found');
    Importer::importRow(['case_id' => 999999999, 'custom_1' => 'x']);
  }

  /**
   * A row for a real Case that carries no repeatable values is rejected.
   */
  public function testImportRowRejectsRowWithNoValues() {
    $caseType = CaseTypeFabricator::fabricate();
    $client = ContactFabricator::fabricate();
    $case = CaseFabricator::fabricate([
      'case_type_id' => $caseType['id'],
      'contact_id' => $client['id'],
      'creator_id' => $client['id'],
    ]);

    $this->expectException(CRM_Core_Exception::class);
    $this->expectExceptionMessage('no repeatable Case custom field values');
    Importer::importRow(['case_id' => $case['id']]);
  }

  /**
   * The mappableFields() helper returns an array (empty with no groups).
   */
  public function testMappableFieldsReturnsArray() {
    $this->assertTrue(is_array(Importer::mappableFields()));
  }

}
