<?php

use Civi\Api4\Action\SearchDisplay\Download;

/**
 * Download Spreadsheet for the records table of a repeatable Case group.
 *
 * Used in place of SearchKit's own action by
 * CRM_Civicase_Event_Listener_RepeatableCaseCustomDataDownload.
 */
class CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataDownload extends Download {

  /**
   * Suffix of the key of a column that shows a file's name.
   */
  const FILE_NAME_SUFFIX = '.file_name';

  /**
   * A copy of a download request, with the same parameters.
   *
   * @param \Civi\Api4\Action\SearchDisplay\Download $download
   *   SearchKit's download request.
   *
   * @return self
   *   The same request, made by this action.
   */
  public static function copyOf(Download $download): self {
    $copy = new self($download->getEntityName(), $download->getActionName());
    foreach ($download->getParams() as $name => $value) {
      $copy->$name = $value;
    }

    return $copy;
  }

  /**
   * Loads the display, then exports its file links instead of file names.
   */
  protected function loadSearchDisplay(): void {
    parent::loadSearchDisplay();
    $columns = $this->display['settings']['columns'] ?? [];
    $this->display['settings']['columns'] = array_map([self::class, 'exportFileUrl'], $columns);
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
    $key = (string) ($column['key'] ?? '');
    if (!str_ends_with($key, self::FILE_NAME_SUFFIX)) {
      return $column;
    }
    $urlToken = '[' . substr($key, 0, -strlen(self::FILE_NAME_SUFFIX)) . '.url]';
    if (($column['link']['path'] ?? NULL) !== $urlToken) {
      return $column;
    }
    $column['rewrite'] = $urlToken;

    return $column;
  }

}
