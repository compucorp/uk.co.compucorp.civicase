<?php

use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Afform\Event\AfformValidateEvent;
use Civi\Api4\Utils\CoreUtil;
use CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAccess as CaseCustomDataAccess;
use CRM_Civicase_Event_Listener_RepeatableCaseCustomDataCaseType as CaseTypeCheck;
use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as CaseCustomAfforms;

/**
 * Form Builder support for repeatable Case custom groups.
 */
class CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAfform {

  /**
   * Prefix of the field blocks core generates for each custom group.
   */
  const BLOCK_PREFIX = 'afblockCustom_';

  /**
   * Afform entity type of a Case.
   */
  const CASE_ENTITY = 'Case';

  /**
   * Civicase's afform module: form styles and per-field error messages.
   */
  const FORM_MODULE = CaseCustomAfforms::FORM_STYLES_MODULE;

  /**
   * Record fields that are not values entered on the form.
   */
  const NON_VALUE_FIELDS = ['id', 'entity_id', 'entity_table'];

  /**
   * Entries taken out of the Case records before core saves them.
   *
   * @var array
   */
  private static $detachedEntries = [];

  /**
   * Listener for `civi.afform.get`: marks required fields in the field blocks.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   The afform.get event; has ->afforms (by-ref) and ->getLayout.
   */
  public static function markRequiredFields($event): void {
    if (empty($event->getLayout)) {
      return;
    }
    $afforms =& $event->afforms;
    foreach ($afforms as $name => $afform) {
      if (!str_starts_with((string) $name, self::BLOCK_PREFIX)
        || empty($afform['join_entity'])
        || !is_string($afform['layout'] ?? NULL)) {
        continue;
      }
      $group = CaseCustomDataAccess::getGatedGroupForEntity($afform['join_entity']);
      if (!$group) {
        continue;
      }
      $afforms[$name]['layout'] = self::addRequiredToLayout(
        $afform['layout'],
        self::getRequiredFieldNames($group)
      );
    }
  }

  /**
   * Listener for `hook_civicrm_angularModules`: loads civicase's form messages.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   The event; has ->angularModules (by-ref).
   */
  public static function addFormMessagesToAfform($event): void {
    $modules =& $event->angularModules;
    if (isset($modules['af'], $modules[self::FORM_MODULE])
      && !in_array(self::FORM_MODULE, $modules['af']['requires'] ?? [], TRUE)) {
      $modules['af']['requires'][] = self::FORM_MODULE;
    }
  }

  /**
   * Settings of civicase's afform module: the repeatable Case groups.
   *
   * @return array
   *   `repeatableCaseEntities`: the APIv4 entity (Custom_<group>) of each
   *   repeatable Case custom group.
   */
  public static function getAngularSettings(): array {
    $groups = \CRM_Core_BAO_CustomGroup::getAll([
      'is_multiple' => TRUE,
      'extends' => CaseCustomAfforms::getCaseExtends(),
    ]);

    return [
      'repeatableCaseEntities' => array_values(array_map(
        fn ($group) => 'Custom_' . $group['name'],
        $groups
      )),
    ];
  }

  /**
   * Adds `defn="{required: true}"` to the given fields of a block layout.
   *
   * @param string $layout
   *   Block layout HTML.
   * @param string[] $requiredFields
   *   Names of the fields to mark as required.
   *
   * @return string
   *   The layout.
   */
  public static function addRequiredToLayout(string $layout, array $requiredFields): string {
    if (!$requiredFields) {
      return $layout;
    }

    return preg_replace_callback(
      '/<af-field\s+name="([^"]+)"\s*\/>/',
      function (array $match) use ($requiredFields) {
        if (!in_array($match[1], $requiredFields, TRUE)) {
          return $match[0];
        }
        return sprintf('<af-field name="%s" defn="{required: true}" />', $match[1]);
      },
      $layout
    );
  }

  /**
   * Listener for `civi.afform.validate`: checks repeatable Case custom data.
   *
   * @param \Civi\Afform\Event\AfformValidateEvent $event
   *   The afform validate event.
   */
  public static function validate(AfformValidateEvent $event): void {
    $allValues = $event->getEntityValues();
    $args = $event->getApiRequest()->getArgs();

    foreach ($event->getFormDataModel()->getEntities() as $entityName => $entity) {
      $isRbac = self::isRbac($entity);
      $ownGroup = CaseCustomDataAccess::getGatedGroupForEntity((string) $entity['type']);

      foreach ($allValues[$entityName] ?? [] as $index => $values) {
        if ($ownGroup) {
          $record = $values['fields'] ?? [];
          if (!self::isBlankEntry($record)) {
            $errors = ($isRbac && !CaseCustomDataAccess::canManageGroup($ownGroup))
              ? [self::permissionError($ownGroup)]
              : self::getEntryErrors($ownGroup, $record, $entity['fields'] ?? []);
            array_map([$event, 'setError'], $errors);
          }
        }

        if ($entity['type'] !== self::CASE_ENTITY) {
          continue;
        }
        $caseId = self::getSubmittedCaseId($values, $args[$entityName] ?? NULL, (int) $index);
        foreach ($values['joins'] ?? [] as $joinEntity => $entries) {
          $group = CaseCustomDataAccess::getGatedGroupForEntity((string) $joinEntity);
          if (!$group || !is_array($entries)) {
            continue;
          }
          $errors = self::getJoinErrors(
            $group,
            $entries,
            $entity['joins'][$joinEntity]['fields'] ?? [],
            $caseId,
            $values['fields']['case_type_id'] ?? NULL,
            $isRbac
          );
          array_map([$event, 'setError'], $errors);
        }
      }
    }
  }

  /**
   * Listener for `civi.afform.validate`, last: shows the user what is wrong.
   *
   * @param \Civi\Afform\Event\AfformValidateEvent $event
   *   The afform validate event.
   *
   * @throws \CRM_Core_Exception
   *   When there are validation errors.
   */
  public static function reportErrors(AfformValidateEvent $event): void {
    $errors = $event->getErrors();
    if (!$errors || !self::hasRepeatableCaseGroups($event->getFormDataModel()->getEntities())) {
      return;
    }
    \Civi::log('afform')->error('Afform Validation errors: ' . print_r($errors, TRUE));

    throw new \CRM_Core_Exception(implode("\n", $errors), 0, ['validation' => $errors]);
  }

  /**
   * Listener for `civi.afform.submit`, before core: detaches the entries.
   *
   * @param \Civi\Afform\Event\AfformSubmitEvent $event
   *   The afform submit event.
   */
  public static function detachEntries(AfformSubmitEvent $event): void {
    if ($event->getEntityType() !== self::CASE_ENTITY) {
      return;
    }
    $key = spl_object_id($event);
    unset(self::$detachedEntries[$key]);

    foreach ($event->records as $index => $record) {
      foreach ($record['joins'] ?? [] as $joinEntity => $entries) {
        if (!CaseCustomDataAccess::getGatedGroupForEntity((string) $joinEntity)) {
          continue;
        }
        self::$detachedEntries[$key][$index]['entries'][$joinEntity] = (array) $entries;
        // Whether the form is updating a case that already existed.
        self::$detachedEntries[$key][$index]['existing'] = !empty($record['fields']['id']);
        unset($event->records[$index]['joins'][$joinEntity]);
      }
    }
  }

  /**
   * Listener for `civi.afform.submit`, after core: saves the entries.
   *
   * @param \Civi\Afform\Event\AfformSubmitEvent $event
   *   The afform submit event.
   */
  public static function saveEntries(AfformSubmitEvent $event): void {
    $key = spl_object_id($event);
    $detached = self::$detachedEntries[$key] ?? [];
    unset(self::$detachedEntries[$key]);
    if (!$detached) {
      return;
    }
    $isRbac = self::isRbac($event->getEntity());

    foreach ($detached as $index => $record) {
      $caseId = (int) $event->getEntityId((int) $index);
      if (!$caseId) {
        continue;
      }
      if ($isRbac && $record['existing'] && !self::canUpdateCase($caseId)) {
        continue;
      }
      foreach ($record['entries'] as $joinEntity => $entries) {
        $group = CaseCustomDataAccess::getGatedGroupForEntity($joinEntity);
        if ($isRbac && !CaseCustomDataAccess::canManageGroup($group)) {
          continue;
        }
        $ids = self::saveCaseEntries($joinEntity, $caseId, $entries);
        $event->setJoinIds($index, $joinEntity, $ids);
      }
    }
  }

  /**
   * Creates or updates a Case's records from the submitted entries.
   *
   * An entry naming a record that is not on this Case creates a new record
   * instead, as core does.
   *
   * @param string $joinEntity
   *   The group's APIv4 entity, Custom_<group>.
   * @param int $caseId
   *   The Case.
   * @param array $entries
   *   The submitted entries, keyed by their position on the form.
   *
   * @return array
   *   The saved records, keyed by the entry's position on the form (for the
   *   file uploads that follow the submission).
   */
  public static function saveCaseEntries(string $joinEntity, int $caseId, array $entries): array {
    $entries = array_filter($entries, fn ($entry) => is_array($entry) && !self::isBlankEntry($entry));
    if (!$entries) {
      return [];
    }
    $ownIds = array_map('intval', self::getCaseRecordIds($joinEntity, $caseId));

    $records = [];
    foreach ($entries as $position => $entry) {
      if (empty($entry['id']) || !in_array((int) $entry['id'], $ownIds, TRUE)) {
        unset($entry['id']);
      }
      unset($entry['entity_table']);
      // Custom value saves need the entity even for an update.
      $entry['entity_id'] = $caseId;
      $records[$position] = $entry;
    }
    $saved = civicrm_api4($joinEntity, 'save', [
      'checkPermissions' => FALSE,
      'records' => array_values($records),
    ]);

    return array_combine(array_keys($records), $saved->getArrayCopy());
  }

  /**
   * The validation errors for the entries of a group joined to a Case.
   *
   * @param array $group
   *   CustomGroup record of a repeatable Case group, with its fields.
   * @param array $entries
   *   The submitted entries.
   * @param array $formFields
   *   The group's fields on the form, by name, with their attributes.
   * @param int|null $caseId
   *   The case being updated, or NULL for a new case.
   * @param mixed $caseTypeId
   *   The submitted case type of a new case, if any.
   * @param bool $isRbac
   *   Whether the Case uses role-based security.
   *
   * @return string[]
   *   Error messages.
   */
  private static function getJoinErrors(array $group, array $entries, array $formFields, ?int $caseId, $caseTypeId, bool $isRbac): array {
    $entries = array_filter($entries, fn ($entry) => is_array($entry) && !self::isBlankEntry($entry));
    if (!$entries) {
      return [];
    }
    if ($isRbac && !CaseCustomDataAccess::canManageGroup($group)) {
      $changes = self::getEntriesThatChangeRecords($group, $entries, $caseId);
      return $changes ? [self::permissionError($group)] : [];
    }
    if (!self::isCaseTypeAllowed($group, $caseId, $caseTypeId)) {
      return [ts('%1 records cannot be added to cases of this type.', [1 => $group['title']])];
    }

    $errors = [];
    foreach ($entries as $position => $entry) {
      $entryNumber = count($entries) > 1 || $position > 0 ? (int) $position + 1 : NULL;
      $errors = array_merge($errors, self::getEntryErrors($group, $entry, $formFields, $entryNumber));
    }

    return $errors;
  }

  /**
   * The validation errors for the values of one entry (one record).
   *
   * @param array $group
   *   CustomGroup record of a repeatable Case group, with its fields.
   * @param array $entry
   *   The entry's values by field name.
   * @param array $formFields
   *   The group's fields on the form, by name, with their attributes.
   * @param int|null $entryNumber
   *   The entry's position on the form, from 1, to name it in messages.
   *
   * @return string[]
   *   Error messages.
   */
  public static function getEntryErrors(array $group, array $entry, array $formFields = [], ?int $entryNumber = NULL): array {
    $fields = self::getGroupFields($group);
    $label = function (string $fieldName) use ($fields, $formFields, $entryNumber) {
      $label = $formFields[$fieldName]['defn']['label'] ?? $fields[$fieldName]['label'];
      return $entryNumber ? ts('%1 (entry %2)', [1 => $label, 2 => $entryNumber]) : $label;
    };

    $errors = [];
    foreach ($formFields as $fieldName => $attributes) {
      if (!empty($fields[$fieldName]['is_required'])
        && !isset($attributes['defn']['required'])
        && $fields[$fieldName]['input_type'] !== 'File'
        && self::isBlank($entry[$fieldName] ?? NULL)) {
        $errors[] = ts('%1 is a required field.', [1 => $label($fieldName)]);
      }
    }
    foreach ($entry as $fieldName => $value) {
      if (!isset($fields[$fieldName])) {
        continue;
      }
      $error = self::getValueError($fields[$fieldName], $value, $label($fieldName));
      if ($error !== NULL) {
        $errors[] = $error;
      }
    }

    return $errors;
  }

  /**
   * The problem with a field's value, if any.
   *
   * @param array $field
   *   The field, as returned by getGroupFields().
   * @param mixed $value
   *   The submitted value.
   * @param string $label
   *   The field's label, to name it in the message.
   *
   * @return string|null
   *   An error message, or NULL if the value is fine. Blank values are left
   *   to the required-field check.
   */
  public static function getValueError(array $field, $value, string $label): ?string {
    if ($field['fk_entity'] === 'File' || $field['input_type'] === 'File') {
      return NULL;
    }
    $values = is_array($value) ? $value : [$value];
    $values = array_filter($values, fn ($item) => !self::isBlank($item));
    if (count($values) > 1 && empty($field['serialize'])) {
      return ts('%1 accepts only one value.', [1 => $label]);
    }

    foreach ($values as $item) {
      if (!is_scalar($item)) {
        return ts('%1 has an invalid value.', [1 => $label]);
      }
      switch ($field['data_type']) {
        case 'Int':
        case 'Integer':
          if (!CRM_Utils_Rule::integer($item)) {
            return ts('%1 must be a whole number.', [1 => $label]);
          }
          break;

        case 'Float':
        case 'Money':
          if (!CRM_Utils_Rule::numeric($item)) {
            return ts('%1 must be a number.', [1 => $label]);
          }
          break;

        case 'Boolean':
          if (!in_array($item, [TRUE, FALSE, 0, 1, '0', '1'], TRUE)) {
            return ts('%1 must be Yes or No.', [1 => $label]);
          }
          // Its options are Yes and No, checked just above.
          continue 2;

        case 'Date':
        case 'Timestamp':
          if (strtotime((string) $item) === FALSE) {
            return ts('%1 must be a valid date.', [1 => $label]);
          }
          break;

        // A "Link" field. Checked as core's save checks it.
        case 'Url':
          if (!CRM_Utils_Rule::url(trim((string) $item))) {
            return ts('%1 must be a full web address, such as https://example.org.', [1 => $label]);
          }
          break;
      }
      if ($field['max_length'] && mb_strlen((string) $item) > $field['max_length']) {
        return ts('%1 must be %2 characters or fewer.', [1 => $label, 2 => $field['max_length']]);
      }
      if (is_array($field['options']) && !array_key_exists((string) $item, $field['options'])) {
        return ts('%1 has an invalid option.', [1 => $label]);
      }
    }

    return NULL;
  }

  /**
   * The group's fields that a form can set, by name.
   *
   * @param array $group
   *   CustomGroup record, with its fields as CRM_Core_BAO_CustomGroup::getGroup
   *   returns them.
   *
   * @return array[]
   *   Each with name, label, data_type, input_type, fk_entity, serialize,
   *   options (value => label, or FALSE), max_length (or NULL) and
   *   is_required.
   */
  private static function getGroupFields(array $group): array {
    $entity = 'Custom_' . $group['name'];
    if (isset(\Civi::$statics[__CLASS__]['fields'][$entity])) {
      return \Civi::$statics[__CLASS__]['fields'][$entity];
    }

    $textLengths = $required = [];
    foreach ($group['fields'] ?? [] as $customField) {
      $textLengths[$customField['name']] = $customField['text_length'] ?? NULL;
      $required[$customField['name']] = !empty($customField['is_required']);
    }
    $fields = [];
    $apiFields = civicrm_api4($entity, 'getFields', [
      'checkPermissions' => FALSE,
      'action' => 'create',
      'loadOptions' => TRUE,
      'select' => ['name', 'label', 'data_type', 'input_type', 'fk_entity', 'serialize', 'options', 'custom_field_id'],
    ]);
    foreach ($apiFields as $field) {
      if (empty($field['custom_field_id'])) {
        continue;
      }
      // An EntityRef's options are not loaded; its values are checked by
      // afform core against what the field's search allows.
      if ($field['input_type'] === 'EntityRef') {
        $field['options'] = FALSE;
      }
      // Text and link fields are stored in a VARCHAR of the field's length
      // (255 when not set); longer values fail to save.
      $field['max_length'] = NULL;
      if (in_array($field['data_type'], ['String', 'Url'], TRUE) && array_key_exists($field['name'], $textLengths)) {
        $field['max_length'] = (int) ($textLengths[$field['name']] ?: 255);
      }
      $field['is_required'] = $required[$field['name']] ?? FALSE;
      $fields[$field['name']] = $field;
    }

    return \Civi::$statics[__CLASS__]['fields'][$entity] = $fields;
  }

  /**
   * The entries that would add a record or change a stored one.
   *
   * @param array $group
   *   CustomGroup record.
   * @param array $entries
   *   Non-blank submitted entries.
   * @param int|null $caseId
   *   The case being updated, or NULL for a new case.
   *
   * @return array
   *   The entries that would write, keyed as given.
   */
  public static function getEntriesThatChangeRecords(array $group, array $entries, ?int $caseId): array {
    $ids = array_filter(array_map(fn ($entry) => (int) ($entry['id'] ?? 0), $entries));
    $stored = [];
    if ($caseId && $ids) {
      $stored = (array) civicrm_api4('Custom_' . $group['name'], 'get', [
        'checkPermissions' => FALSE,
        'where' => [['id', 'IN', array_values($ids)], ['entity_id', '=', $caseId]],
      ])->indexBy('id');
    }

    return array_filter($entries, function ($entry) use ($stored) {
      $record = $stored[(int) ($entry['id'] ?? 0)] ?? NULL;
      if ($record === NULL) {
        return TRUE;
      }
      foreach ($entry as $fieldName => $value) {
        if (in_array($fieldName, self::NON_VALUE_FIELDS, TRUE)) {
          continue;
        }
        if (self::normaliseValue($value) !== self::normaliseValue($record[$fieldName] ?? NULL)) {
          return TRUE;
        }
      }
      return FALSE;
    });
  }

  /**
   * A value in a form that compares equal however it was submitted or stored.
   *
   * @param mixed $value
   *   A submitted or stored value.
   *
   * @return string
   *   Comparable form of the value.
   */
  private static function normaliseValue($value): string {
    if (is_array($value)) {
      $items = array_map(
        [self::class, 'normaliseValue'],
        array_filter($value, fn ($item) => !self::isBlank($item))
      );
      sort($items);
      return implode("\x01", $items);
    }
    if (is_bool($value)) {
      return $value ? '1' : '0';
    }
    $value = trim((string) $value);
    if (is_numeric($value)) {
      return (string) (float) $value;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) && ($time = strtotime($value)) !== FALSE) {
      return date('YmdHis', $time);
    }

    return $value;
  }

  /**
   * Whether a group's records may be added to a case.
   *
   * @param array $group
   *   CustomGroup record.
   * @param int|null $caseId
   *   The case being updated, or NULL for a new case.
   * @param mixed $caseTypeId
   *   The submitted case type of a new case, if any.
   *
   * @return bool
   *   FALSE when the group is limited to other case types. TRUE when the case
   *   type is not known (the save itself is still checked).
   */
  private static function isCaseTypeAllowed(array $group, ?int $caseId, $caseTypeId): bool {
    if ($caseId) {
      return !CaseTypeCheck::getRecordsOnOtherCaseTypes($group, [['entity_id' => $caseId]]);
    }
    $allowedTypes = CaseCustomAfforms::getGroupCaseTypeIds($group);
    if ($allowedTypes === NULL || !is_numeric($caseTypeId)) {
      return TRUE;
    }

    return in_array((int) $caseTypeId, $allowedTypes, TRUE);
  }

  /**
   * The existing case a Case entity of the form is updating, if any.
   *
   * @param array $values
   *   The entity's submitted values.
   * @param mixed $arg
   *   The form's argument for the entity: an id, or a list of ids by index.
   * @param int $index
   *   The entity's index on the form.
   *
   * @return int|null
   *   Case id.
   */
  private static function getSubmittedCaseId(array $values, $arg, int $index): ?int {
    $caseId = $values['fields']['id'] ?? NULL;
    if (!$caseId && $arg !== NULL) {
      $caseId = is_array($arg) ? ($arg[$index] ?? NULL) : ($index === 0 ? $arg : NULL);
    }

    return is_numeric($caseId) && (int) $caseId > 0 ? (int) $caseId : NULL;
  }

  /**
   * The ids of a case's records in a group.
   *
   * @param string $joinEntity
   *   The group's APIv4 entity, Custom_<group>.
   * @param int $caseId
   *   The Case.
   *
   * @return array
   *   Record ids.
   */
  private static function getCaseRecordIds(string $joinEntity, int $caseId): array {
    return civicrm_api4($joinEntity, 'get', [
      'checkPermissions' => FALSE,
      'select' => ['id'],
      'where' => [['entity_id', '=', $caseId]],
    ])->column('id');
  }

  /**
   * Whether the logged-in user may update a case.
   *
   * @param int $caseId
   *   The Case.
   *
   * @return bool
   *   The APIv4 access check for updating the case.
   */
  private static function canUpdateCase(int $caseId): bool {
    return CoreUtil::checkAccessDelegated(
      self::CASE_ENTITY,
      'update',
      ['id' => $caseId],
      (int) CRM_Core_Session::getLoggedInContactID()
    );
  }

  /**
   * Field names of a group's active required fields.
   *
   * @param array $group
   *   CustomGroup record, with its fields.
   *
   * @return string[]
   *   Field names.
   */
  private static function getRequiredFieldNames(array $group): array {
    $names = [];
    foreach ($group['fields'] ?? [] as $field) {
      if (!empty($field['is_required']) && !empty($field['is_active'])) {
        $names[] = $field['name'];
      }
    }

    return $names;
  }

  /**
   * Whether an entry has no value.
   *
   * An entry naming a record is never blank. Otherwise it needs a value other
   * than the link to its Case. Unlike core's check for joins, 0 and "No"
   * count as values, so an entry with only those is saved.
   *
   * @param array $entry
   *   The entry's values by field name.
   *
   * @return bool
   *   TRUE if the entry is blank.
   */
  private static function isBlankEntry(array $entry): bool {
    if (!empty($entry['id'])) {
      return FALSE;
    }
    $values = array_diff_key($entry, array_flip(self::NON_VALUE_FIELDS));

    return self::isBlank($values);
  }

  /**
   * Whether a value is blank.
   *
   * @param mixed $value
   *   A value.
   *
   * @return bool
   *   TRUE for NULL, an empty string, or a list whose items are all blank.
   *   0 and FALSE are values.
   */
  private static function isBlank($value): bool {
    if (is_array($value)) {
      return !array_filter($value, fn ($item) => !self::isBlank($item));
    }

    return $value === NULL || $value === '';
  }

  /**
   * Whether a form has a repeatable Case group, as an entity or a Case join.
   *
   * @param array $entities
   *   The form's entities, as the form data model returns them.
   *
   * @return bool
   *   TRUE if any entity or Case join is a repeatable Case group.
   */
  private static function hasRepeatableCaseGroups(array $entities): bool {
    foreach ($entities as $entity) {
      if (CaseCustomDataAccess::getGatedGroupForEntity((string) $entity['type'])) {
        return TRUE;
      }
      if ($entity['type'] !== self::CASE_ENTITY) {
        continue;
      }
      foreach (array_keys($entity['joins'] ?? []) as $joinEntity) {
        if (CaseCustomDataAccess::getGatedGroupForEntity((string) $joinEntity)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Whether a form entity uses role-based security (the default).
   *
   * @param array $entity
   *   The form entity.
   *
   * @return bool
   *   TRUE unless the entity uses form-based security.
   */
  private static function isRbac(array $entity): bool {
    return ($entity['security'] ?? 'RBAC') !== 'FBAC';
  }

  /**
   * The error for a user who may not edit a group's records.
   *
   * @param array $group
   *   CustomGroup record.
   *
   * @return string
   *   Error message.
   */
  private static function permissionError(array $group): string {
    return ts('You do not have permission to edit %1 records.', [1 => $group['title']]);
  }

}
