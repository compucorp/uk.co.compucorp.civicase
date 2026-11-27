<?php

use Civi\API\Event\PrepareEvent;
use Civi\Api4\Generic\AbstractAction;
use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Keeps cleared Money and Float values of repeatable Case groups empty.
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataClearedNumbers {

  /**
   * Custom field data types CiviCRM saves as 0 when blank.
   */
  const DATA_TYPES = ['Money', 'Float'];

  /**
   * APIv4 actions that write the values they are given.
   */
  const ACTIONS = ['create', 'update', 'save'];

  /**
   * Listener for `civi.api.prepare`: wraps writes to repeatable Case groups.
   *
   * @param \Civi\API\Event\PrepareEvent $event
   *   The API prepare event.
   */
  public static function keepClearedNumbersEmpty(PrepareEvent $event): void {
    $request = $event->getApiRequest();
    if (!$request instanceof AbstractAction
      || !in_array($request->getActionName(), self::ACTIONS, TRUE)
      || !str_starts_with($request->getEntityName(), 'Custom_')
    ) {
      return;
    }
    $group = self::getRepeatableCaseGroup(substr($request->getEntityName(), strlen('Custom_')));
    $columns = $group ? self::getNumberColumns($group) : [];
    if (!$columns) {
      return;
    }

    $event->wrapApi(function (AbstractAction $request, callable $continue) use ($group, $columns) {
      $isSave = $request->getActionName() === 'save';
      $blank = $isSave
        ? array_map(fn ($record) => self::getBlankFields($record + $request->getDefaults(), $columns), array_values($request->getRecords()))
        : self::getBlankFields($request->getValues(), $columns);

      $result = $continue($request);

      $idsByFields = [];
      $position = 0;
      foreach ($result as $record) {
        $fields = $isSave ? ($blank[$position++] ?? []) : $blank;
        if ($fields && !empty($record['id'])) {
          $idsByFields[implode(',', $fields)][] = (int) $record['id'];
        }
      }
      foreach ($idsByFields as $fields => $ids) {
        self::emptyValues($group['table_name'], array_intersect_key($columns, array_flip(explode(',', $fields))), $ids);
      }

      return $result;
    });
  }

  /**
   * A repeatable Case group, with its fields, by name.
   *
   * @param string $name
   *   Custom group name.
   *
   * @return array|null
   *   The group as CRM_Core_BAO_CustomGroup::getGroup() returns it, or NULL
   *   when it is not a repeatable Case group.
   */
  private static function getRepeatableCaseGroup(string $name): ?array {
    $service = new CaseCustomAfforms();
    if (!in_array($name, array_column($service->getRepeatableCaseGroups(), 'name'), TRUE)) {
      return NULL;
    }

    return CRM_Core_BAO_CustomGroup::getGroup(['name' => $name]);
  }

  /**
   * The group's Money and Float fields.
   *
   * @param array $group
   *   The group, with its fields.
   *
   * @return array
   *   Column names, keyed by field name.
   */
  private static function getNumberColumns(array $group): array {
    $columns = [];
    foreach ($group['fields'] ?? [] as $field) {
      if (in_array($field['data_type'], self::DATA_TYPES, TRUE)) {
        $columns[$field['name']] = $field['column_name'];
      }
    }

    return $columns;
  }

  /**
   * The Money and Float fields a write gives a blank value.
   *
   * @param array $values
   *   The values written, keyed by field name.
   * @param array $columns
   *   The group's Money and Float columns, keyed by field name.
   *
   * @return string[]
   *   Field names.
   */
  private static function getBlankFields(array $values, array $columns): array {
    $blank = array_filter(
      array_intersect_key($values, $columns),
      fn ($value) => $value === NULL || $value === ''
    );

    return array_keys($blank);
  }

  /**
   * Empties columns of the given records.
   *
   * @param string $table
   *   The group's table.
   * @param array $columns
   *   The columns to empty.
   * @param int[] $ids
   *   The records.
   */
  private static function emptyValues(string $table, array $columns, array $ids): void {
    $set = array_map(fn ($column) => "`$column` = NULL", $columns);
    CRM_Core_DAO::executeQuery(sprintf(
      'UPDATE `%s` SET %s WHERE id IN (%s)',
      $table,
      implode(', ', $set),
      implode(', ', array_map('intval', $ids))
    ));
  }

}
