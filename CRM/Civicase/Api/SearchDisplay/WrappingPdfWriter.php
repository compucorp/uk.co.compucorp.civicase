<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf;

/**
 * PDF writer that lets the text of some columns wrap at any character.
 *
 * The URLs in those columns are written as links, so they stay clickable.
 */
class CRM_Civicase_Api_SearchDisplay_WrappingPdfWriter extends Dompdf {

  /**
   * Columns whose text may wrap at any character, numbered from 0.
   *
   * PhpSpreadsheet creates the writer itself, so they are set here first.
   *
   * @var int[]
   */
  public static $wrapColumns = [];

  /**
   * Creates the writer, which adds the wrapping style to its HTML.
   *
   * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
   *   The spreadsheet to write.
   */
  public function __construct(Spreadsheet $spreadsheet) {
    parent::__construct($spreadsheet);
    $this->setEditHtmlCallback([$this, 'addWrapStyle']);
  }

  /**
   * Adds a style letting the wrap columns' cells break their text anywhere.
   *
   * @param string $html
   *   The spreadsheet as HTML.
   *
   * @return string
   *   The HTML with the style added to its head.
   */
  public function addWrapStyle(string $html): string {
    if (!self::$wrapColumns) {
      return $html;
    }
    $selectors = array_map(fn ($number) => '.column' . (int) $number, self::$wrapColumns);
    $style = '<style>' . implode(', ', $selectors) . ' { overflow-wrap: anywhere; }</style>';

    return preg_replace('~</head>~i', $style . '</head>', $html, 1) ?? $html;
  }

  /**
   * Generates the HTML, with the URLs in the wrap columns as links.
   *
   * The cells only hold the URL as text. PDF viewers make such text clickable
   * only when they can spot the whole URL, which they cannot once it wraps
   * over several lines. As a link, Dompdf adds a link to each of its lines.
   *
   * @return string
   *   The spreadsheet as HTML.
   */
  public function generateHtmlAll() {
    $this->linkWrapColumnUrls();

    return parent::generateHtmlAll();
  }

  /**
   * Adds a hyperlink to the cells of the wrap columns that hold a web URL.
   */
  private function linkWrapColumnUrls(): void {
    foreach (self::$wrapColumns as $number) {
      $column = Coordinate::stringFromColumnIndex((int) $number + 1);
      foreach ($this->spreadsheet->getAllSheets() as $sheet) {
        // Read once: it scans every cell of the sheet.
        $highestRow = $sheet->getHighestDataRow($column);
        for ($row = 1; $row <= $highestRow; $row++) {
          $cell = $sheet->getCell($column . $row);
          $value = $cell->getValue();
          if (is_string($value) && preg_match('~^https?://\S+$~i', $value)) {
            $cell->getHyperlink()->setUrl($value);
          }
        }
      }
    }
  }

}
