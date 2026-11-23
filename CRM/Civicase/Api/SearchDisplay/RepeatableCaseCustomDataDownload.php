<?php

use Civi\Api4\Action\SearchDisplay\Download;
use Civi\Api4\Result\SearchDisplayRunResult;
use CRM_Civicase_Api_SearchDisplay_WrappingPdfWriter as WrappingPdfWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Download Spreadsheet for the records table of a repeatable Case group.
 *
 * Used in place of SearchKit's own action by
 * CRM_Civicase_Event_Listener_RepeatableCaseCustomDataFileLinks.
 */
class CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataDownload extends Download {

  use CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataFileLinksTrait;

  /**
   * Suffix of the key of a column that shows a file's name.
   */
  const FILE_NAME_SUFFIX = '.file_name';

  /**
   * The PhpSpreadsheet writer SearchKit makes PDFs with.
   */
  const PDF_WRITER = 'Dompdf';

  /**
   * Loads the display, then exports its file links instead of file names.
   */
  protected function loadSearchDisplay(): void {
    parent::loadSearchDisplay();
    $columns = $this->display['settings']['columns'] ?? [];
    $this->display['settings']['columns'] = array_map([self::class, 'exportFileUrl'], $columns);
  }

  /**
   * Exports the display; in a PDF, file links wrap to fit their column.
   *
   * @param \Civi\Api4\Result\SearchDisplayRunResult $result
   *   The result.
   */
  protected function processResult(SearchDisplayRunResult $result) {
    $wrapColumns = $this->format === 'pdf'
      ? self::getFileUrlColumnNumbers($this->display['settings']['columns'] ?? [])
      : [];
    if (!$wrapColumns) {
      parent::processResult($result);
      return;
    }

    $writer = get_class(IOFactory::createWriter(new Spreadsheet(), self::PDF_WRITER));
    WrappingPdfWriter::$wrapColumns = $wrapColumns;
    IOFactory::registerWriter(self::PDF_WRITER, WrappingPdfWriter::class);
    try {
      parent::processResult($result);
    }
    finally {
      IOFactory::registerWriter(self::PDF_WRITER, $writer);
      WrappingPdfWriter::$wrapColumns = [];
    }
  }

  /**
   * Makes a column that shows a file name linked to the file export the link.
   *
   * @param array $column
   *   A column of the display's settings.
   *
   * @return array
   *   The column to export.
   */
  public static function exportFileUrl(array $column): array {
    $urlToken = self::getFileUrlToken($column);
    if ($urlToken !== NULL) {
      $column['rewrite'] = $urlToken;
    }

    return $column;
  }

  /**
   * The exported columns that hold a file link, numbered from 0.
   *
   * @param array $columns
   *   The columns of the display's settings.
   *
   * @return int[]
   *   Column numbers in the exported file.
   */
  public static function getFileUrlColumnNumbers(array $columns): array {
    $exported = array_values(array_filter($columns, fn ($column) => !empty($column['key'])));
    $fileUrls = array_filter($exported, fn ($column) => self::getFileUrlToken($column) !== NULL);

    return array_keys($fileUrls);
  }

  /**
   * The token of a file's link, for a column showing the file name linked.
   *
   * @param array $column
   *   A column of the display's settings.
   *
   * @return string|null
   *   For example `[record_file_attachment.url]`, or NULL for other columns.
   */
  private static function getFileUrlToken(array $column): ?string {
    $key = (string) ($column['key'] ?? '');
    if (!str_ends_with($key, self::FILE_NAME_SUFFIX)) {
      return NULL;
    }
    $urlToken = '[' . substr($key, 0, -strlen(self::FILE_NAME_SUFFIX)) . '.url]';

    return ($column['link']['path'] ?? NULL) === $urlToken ? $urlToken : NULL;
  }

}
