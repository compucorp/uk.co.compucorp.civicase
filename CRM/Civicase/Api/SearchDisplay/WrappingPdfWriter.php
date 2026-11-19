<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf;

/**
 * PDF writer that lets the text of some columns wrap at any character.
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

}
