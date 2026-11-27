<?php

use Civi\Test;
use Civi\Test\HeadlessInterface;
use CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataDownload as FileUrlDownload;
use CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataRun as RecordsRun;
use CRM_Civicase_Api_SearchDisplay_WrappingPdfWriter as WrappingPdfWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
   * Permissions of a user who works on every award case, and sees its data.
   */
  const AWARD_USER_PERMISSIONS = [
    'access CiviCRM',
    'view all contacts',
    'access all custom data',
    'access all award and activities',
  ];

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
   * Removes the files, groups, Cases, case types and contacts the test created.
   */
  public function tearDown(): void {
    $this->resetPermissions();
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
   * Download Spreadsheet exports the file's link, not just its name.
   */
  public function testDownloadExportsTheFileLink() {
    $group = $this->createRepeatableCaseGroup('Documents', [
      'attachment' => [
        'data_type' => 'File',
        'html_type' => 'File',
        'in_selector' => 1,
      ],
      'title' => ['in_selector' => 1],
    ]);
    $caseId = $this->createCase();
    $fileId = $this->createFile('report');
    $this->createRecord($group, $caseId, ['attachment' => $fileId, 'title' => 'With file']);
    $this->createRecord($group, $caseId, ['title' => 'No file']);

    $rows = (array) civicrm_api4('SearchDisplay', 'download', [
      'savedSearch' => 'Custom_' . $group['name'] . '_Search',
      'display' => 'Custom_' . $group['name'] . '_Tab',
      'filters' => ['entity_id' => $caseId],
      'format' => 'array',
      'checkPermissions' => FALSE,
    ]);

    $this->assertEquals(['attachment', 'title'], $rows[0]);
    $this->assertCount(3, $rows);
    $this->assertEquals(['With file', 'No file'], [$rows[1][1], $rows[2][1]]);
    parse_str((string) parse_url($rows[1][0], PHP_URL_QUERY), $query);
    $this->assertEquals($fileId, $query['id'] ?? NULL);
    $entityId = $query['eid'] ?? NULL;
    $this->assertTrue(CRM_Core_BAO_File::validateFileHash($query['fcs'] ?? '', $entityId, $fileId));
    [$path] = CRM_Core_BAO_File::path($fileId, $entityId);
    $this->assertNotNull($path, 'The exported link must find the file.');
    $this->assertEmpty($rows[2][0]);
    $this->assertEquals('report.txt', $this->runTable($group, $caseId)[0]['columns'][0]['val']);
  }

  /**
   * A user with only a case category permission gets the table's file links.
   *
   * Such a user cannot join the Case, which used to make the table fail.
   * Like any user shown files, they need "access uploaded files": without it
   * APIv4 leaves out the File join, as core's download page would refuse them.
   */
  public function testCaseCategoryUserGetsFileLinks() {
    [$group, $caseId, $fileId] = $this->createFileRecordOnAwardCase();
    $this->setPermissions(array_merge(self::AWARD_USER_PERMISSIONS, ['access uploaded files']));

    $column = $this->runTable($group, $caseId, TRUE)[0]['columns'][0];

    $this->assertEquals('report.txt', $column['val']);
    parse_str((string) parse_url($column['links'][0]['url'], PHP_URL_QUERY), $query);
    $this->assertEquals($fileId, $query['id'] ?? NULL);
    $this->assertEquals($caseId, $query['eid'] ?? NULL);
    $this->assertTrue(CRM_Core_BAO_File::validateFileHash($query['fcs'] ?? '', $caseId, $fileId));
    [$path] = CRM_Core_BAO_File::path($fileId, $caseId);
    $this->assertNotNull($path, 'The download link must find the file.');
  }

  /**
   * Without "access uploaded files" the table loads, with no file or link.
   */
  public function testUserWithoutFileAccessGetsNoFileLinks() {
    [$group, $caseId] = $this->createFileRecordOnAwardCase();
    $this->setPermissions(self::AWARD_USER_PERMISSIONS);

    $rows = $this->runTable($group, $caseId, TRUE);

    $this->assertCount(1, $rows);
    $this->assertNull($rows[0]['columns'][0]['val'] ?? NULL);
    $this->assertEmpty($rows[0]['columns'][0]['links'] ?? NULL);
  }

  /**
   * Creates a group with a File field, and a record with a file on an award.
   *
   * @return array
   *   The group, the award Case id and the file id.
   */
  private function createFileRecordOnAwardCase(): array {
    $group = $this->createRepeatableCaseGroup('Documents', [
      'attachment' => [
        'data_type' => 'File',
        'html_type' => 'File',
        'in_selector' => 1,
      ],
    ]);
    $caseId = $this->createCategoryCase('award');
    $fileId = $this->createFile('report');
    $this->createRecord($group, $caseId, ['attachment' => $fileId]);

    return [$group, $caseId, $fileId];
  }

  /**
   * A joined file is linked through the record's Case; other links are kept.
   */
  public function testFileLinksGoThroughTheRecordsCase() {
    $row = RecordsRun::linkFilesToCase([
      'entity_id' => 51,
      'record_file_attachment.id' => 7,
      'record_file_attachment.url' => 'https://example.org/record-link',
      'record_file_empty.id' => NULL,
      'record_file_empty.url' => 'https://example.org/no-file',
      'record_file_unselected.url' => 'https://example.org/kept',
      'other.url' => 'https://example.org/other',
    ]);

    parse_str((string) parse_url($row['record_file_attachment.url'], PHP_URL_QUERY), $query);
    $this->assertEquals(['7', '51'], [$query['id'] ?? NULL, $query['eid'] ?? NULL]);
    $this->assertTrue(CRM_Core_BAO_File::validateFileHash($query['fcs'] ?? '', 51, 7));
    $this->assertNull($row['record_file_empty.url']);
    $this->assertEquals('https://example.org/kept', $row['record_file_unselected.url']);
    $this->assertEquals('https://example.org/other', $row['other.url']);
  }

  /**
   * A PDF lets only the file link columns wrap, counted as exported.
   *
   * SearchKit leaves columns without a key, such as buttons, out of the file.
   */
  public function testPdfWrapsOnlyFileLinkColumns() {
    $columns = [
      ['type' => 'buttons', 'links' => []],
      ['key' => 'title', 'type' => 'field'],
      [
        'key' => 'record_file_attachment.file_name',
        'type' => 'field',
        'link' => ['path' => '[record_file_attachment.url]'],
      ],
      [
        'key' => 'record_file_other.file_name',
        'type' => 'field',
        'link' => ['path' => '[elsewhere]'],
      ],
    ];

    $this->assertSame([1], FileUrlDownload::getFileUrlColumnNumbers($columns));
  }

  /**
   * The PDF's HTML lets the given columns' cells break their text anywhere.
   */
  public function testPdfWriterAddsWrapStyleForGivenColumns() {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray([
      ['Title', 'Attachment'],
      ['Report', 'https://example.org/civicrm/file?reset=1&id=1&eid=2&fcs=' . str_repeat('a', 64)],
    ]);

    try {
      WrappingPdfWriter::$wrapColumns = [1];
      $html = (new WrappingPdfWriter($spreadsheet))->generateHTMLAll();
      WrappingPdfWriter::$wrapColumns = [];
      $unchanged = (new WrappingPdfWriter($spreadsheet))->generateHTMLAll();
    }
    finally {
      WrappingPdfWriter::$wrapColumns = [];
    }

    $this->assertStringContainsString('<style>.column1 { overflow-wrap: anywhere; }</style></head>', $html);
    $this->assertStringNotContainsString('overflow-wrap', $unchanged);
  }

  /**
   * The PDF links URLs in the given columns, so wrapped ones stay clickable.
   */
  public function testPdfWriterLinksUrlsInGivenColumns() {
    $url = 'https://example.org/civicrm/file?reset=1&id=1&eid=2&fcs=' . str_repeat('a', 64);
    $rows = [
      ['Title', 'Attachment'],
      ['https://example.org/title', $url],
      ['Report', 'No file'],
    ];
    $wrapped = new Spreadsheet();
    $wrapped->getActiveSheet()->fromArray($rows);
    $unwrapped = new Spreadsheet();
    $unwrapped->getActiveSheet()->fromArray($rows);

    try {
      WrappingPdfWriter::$wrapColumns = [1];
      $html = (new WrappingPdfWriter($wrapped))->generateHTMLAll();
      WrappingPdfWriter::$wrapColumns = [];
      $unchanged = (new WrappingPdfWriter($unwrapped))->generateHTMLAll();
    }
    finally {
      WrappingPdfWriter::$wrapColumns = [];
    }

    $this->assertSame(1, substr_count($html, '<a href='));
    $this->assertStringContainsString('<a href="' . htmlspecialchars($url) . '"', $html);
    $this->assertStringNotContainsString('<a href=', $unchanged);
  }

  /**
   * Runs the group's records table for a Case.
   *
   * @param array $group
   *   A group returned by createRepeatableCaseGroup().
   * @param int $caseId
   *   The Case.
   * @param bool $checkPermissions
   *   Whether to run it as the logged-in user.
   *
   * @return array
   *   The table rows.
   */
  private function runTable(array $group, int $caseId, bool $checkPermissions = FALSE): array {
    return (array) civicrm_api4('SearchDisplay', 'run', [
      'savedSearch' => 'Custom_' . $group['name'] . '_Search',
      'display' => 'Custom_' . $group['name'] . '_Tab',
      'filters' => ['entity_id' => $caseId],
      'checkPermissions' => $checkPermissions,
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
