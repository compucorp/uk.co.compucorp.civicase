<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the File column of a repeatable Case group's records table.
 *
 * @group headless
 */
class CRM_Civicase_Service_RepeatableCaseCustomGroupFileColumnTest extends TestCase implements HeadlessInterface {

  use CRM_Civicase_Helpers_SessionTrait;
  use CRM_Civicase_Helpers_RepeatableCaseCustomDataTrait;

  /**
   * Files the test created.
   *
   * @var int[]
   */
  private $fileIds = [];

  /**
   * Paths of the files the test wrote.
   *
   * @var string[]
   */
  private $paths = [];

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
   * Logs in a contact, who creates the Cases.
   */
  public function setUp(): void {
    $this->registerCurrentLoggedInContactInSession($this->createContact());
  }

  /**
   * Removes the files, groups, Cases and contacts the test created.
   */
  public function tearDown(): void {
    foreach ($this->fileIds as $fileId) {
      CRM_Core_DAO::executeQuery('DELETE FROM civicrm_entity_file WHERE file_id = %1', [1 => [$fileId, 'Integer']]);
      CRM_Core_DAO::executeQuery('DELETE FROM civicrm_file WHERE id = %1', [1 => [$fileId, 'Integer']]);
    }
    foreach ($this->paths as $path) {
      if (file_exists($path)) {
        unlink($path);
      }
    }
    $this->fileIds = [];
    $this->paths = [];
    $this->cleanUpRepeatableCaseCustomData();
  }

  /**
   * The column shows the file name, linked to a URL that finds the file.
   */
  public function testFileColumnShowsFileNameLinkedToTheFile() {
    $group = $this->createRepeatableCaseGroup('Documents', [
      'attachment' => [
        'data_type' => 'File',
        'html_type' => 'File',
        'in_selector' => 1,
      ],
    ]);
    $caseId = $this->createCase();
    $fileId = $this->createFile('report');
    $this->createRecord($group, $caseId, ['attachment' => $fileId]);

    $column = $this->runTable($group, $caseId)[0]['columns'][0];

    $this->assertEquals('report.txt', $column['val']);
    parse_str((string) parse_url($column['links'][0]['url'], PHP_URL_QUERY), $query);
    $this->assertEquals($fileId, $query['id']);
    $entityId = $query['eid'] ?? NULL;
    $this->assertTrue(CRM_Core_BAO_File::validateFileHash($query['fcs'], $entityId, $fileId));
    [$path] = CRM_Core_BAO_File::path($fileId, $entityId);
    $this->assertNotNull($path, 'The download link must find the file.');
  }

  /**
   * A record without a file shows an empty, unlinked cell.
   */
  public function testRecordWithoutFileShowsNothing() {
    $group = $this->createRepeatableCaseGroup('Documents', [
      'attachment' => [
        'data_type' => 'File',
        'html_type' => 'File',
        'in_selector' => 1,
      ],
      'title' => ['in_selector' => 1],
    ]);
    $caseId = $this->createCase();
    $this->createRecord($group, $caseId, ['title' => 'No file']);

    $column = $this->runTable($group, $caseId)[0]['columns'][0];

    $this->assertEmpty($column['val'] ?? NULL);
    $this->assertEmpty($column['links'][0]['url'] ?? NULL);
  }

  /**
   * Runs the group's records table for a Case.
   *
   * @param array $group
   *   A group returned by createRepeatableCaseGroup().
   * @param int $caseId
   *   The Case.
   *
   * @return array
   *   The table rows.
   */
  private function runTable(array $group, int $caseId): array {
    return (array) civicrm_api4('SearchDisplay', 'run', [
      'savedSearch' => 'Custom_' . $group['name'] . '_Search',
      'display' => 'Custom_' . $group['name'] . '_Tab',
      'filters' => ['entity_id' => $caseId],
      'checkPermissions' => FALSE,
    ]);
  }

  /**
   * Stores an uploaded text file, on disk and in civicrm_file.
   *
   * @param string $name
   *   The file name, without its extension.
   *
   * @return int
   *   The file id.
   */
  private function createFile(string $name): int {
    $uri = $name . '_' . md5(uniqid('', TRUE)) . '.txt';
    $path = CRM_Core_Config::singleton()->customFileUploadDir . $uri;
    file_put_contents($path, 'Test file');
    $this->paths[] = $path;

    $file = CRM_Core_BAO_File::writeRecord([
      'uri' => $uri,
      'mime_type' => 'text/plain',
    ]);
    $this->fileIds[] = (int) $file->id;

    return (int) $file->id;
  }

}
