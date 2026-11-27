<?php

use Civi\Api4\Action\SearchDisplay\AbstractRunAction;
use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Links the files in a repeatable Case group's records table to their Case.
 */
trait CRM_Civicase_Api_SearchDisplay_RepeatableCaseCustomDataFileLinksTrait {

  /**
   * A copy of a SearchKit request, made by this action.
   *
   * @param \Civi\Api4\Action\SearchDisplay\AbstractRunAction $request
   *   SearchKit's request.
   *
   * @return self
   *   The same request, with the same parameters.
   */
  public static function copyOf(AbstractRunAction $request): self {
    $copy = new self($request->getEntityName(), $request->getActionName());
    foreach ($request->getParams() as $name => $value) {
      $copy->$name = $value;
    }

    return $copy;
  }

  /**
   * Formats the rows once their files are linked to the record's Case.
   *
   * @param iterable $result
   *   The rows the records search returned.
   *
   * @return array
   *   The formatted rows.
   */
  protected function formatResult(iterable $result): array {
    $rows = [];
    foreach ($result as $index => $row) {
      $rows[$index] = self::linkFilesToCase($row);
    }

    return parent::formatResult($rows);
  }

  /**
   * Replaces the links of a row's files with links through its Case.
   *
   * @param array $row
   *   A row of the records search.
   *
   * @return array
   *   The row. A file link is left alone when the row lacks the file's id or
   *   the record's Case, e.g. for a search saved before files were joined
   *   this way.
   */
  public static function linkFilesToCase(array $row): array {
    foreach (array_keys($row) as $key) {
      if (!str_starts_with($key, CaseCustomAfforms::FILE_JOIN_PREFIX) || !str_ends_with($key, '.url')) {
        continue;
      }
      $fileIdKey = substr($key, 0, -strlen('.url')) . '.id';
      if (!array_key_exists($fileIdKey, $row) || !array_key_exists('entity_id', $row)) {
        continue;
      }
      $row[$key] = $row[$fileIdKey] && $row['entity_id']
        ? self::getFileUrl((int) $row[$fileIdKey], (int) $row['entity_id'])
        : NULL;
    }

    return $row;
  }

  /**
   * The link to a file stored against a Case, signed for that Case.
   *
   * Built here rather than with core's file URL formatter, which on newer
   * cores takes a file id instead of this URL.
   *
   * @param int $fileId
   *   The file.
   * @param int $caseId
   *   The Case the file is stored against.
   *
   * @return string
   *   The file's absolute download URL.
   */
  public static function getFileUrl(int $fileId, int $caseId): string {
    $fileHash = CRM_Core_BAO_File::generateFileHash($caseId, $fileId);

    return (string) Civi::url("frontend://civicrm/file?reset=1&id=$fileId&eid=$caseId&fcs=$fileHash", 'a');
  }

}
