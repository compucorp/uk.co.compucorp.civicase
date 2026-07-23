<?php

/**
 * Tests the CaseCustomImporter.create API (TCOSB-64 / 1.8).
 *
 * The endpoint nz.co.fuzion.csvimport calls once per CSV row to import
 * repeatable Case custom data. Covers the field metadata and error handling;
 * the happy-path create (needs a real repeatable custom group) is covered by
 * the Playwright E2E import-repeatable-case-custom-data.spec.ts.
 *
 * @group headless
 */
class CaseCustomImporterApiTest extends BaseHeadlessTest {

  /**
   * The getfields action exposes case_id as a mappable column.
   */
  public function testGetfieldsExposesCaseId() {
    $result = civicrm_api3('CaseCustomImporter', 'getfields', [
      'action' => 'create',
    ]);
    $this->assertArrayHasKey('case_id', $result['values']);
  }

  /**
   * The entity is resolvable via APIv4 getFields, not only api3.
   *
   * The nz.co.fuzion.csvimport extension calls APIv4 getFields on the import
   * entity, so it must exist in api4 too — the gap the api3-only tests missed.
   */
  public function testApi4GetFieldsResolves() {
    $names = civicrm_api4('CaseCustomImporter', 'getFields', [
      'checkPermissions' => FALSE,
    ])->column('name');
    $this->assertContains('case_id', $names);
  }

  /**
   * A missing case_id is rejected by the API.
   */
  public function testCreateRequiresCaseId() {
    try {
      civicrm_api3('CaseCustomImporter', 'create', ['custom_1' => 'x']);
      $this->fail('Expected an API error when case_id is missing');
    }
    catch (Exception $e) {
      $this->assertNotFalse(strpos($e->getMessage(), 'case_id'));
    }
  }

  /**
   * A non-existent Case is rejected by the API.
   */
  public function testCreateRejectsUnknownCase() {
    try {
      civicrm_api3('CaseCustomImporter', 'create', [
        'case_id' => 999999999,
        'custom_1' => 'x',
      ]);
      $this->fail('Expected an API error for an unknown Case');
    }
    catch (Exception $e) {
      $this->assertNotFalse(strpos($e->getMessage(), 'not found'));
    }
  }

}
