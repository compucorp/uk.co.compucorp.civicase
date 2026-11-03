<?php

use Civi\Api4\CustomGroup;
use Civi\Api4\Managed;
use Civi\Api4\Utils\CoreUtil;

/**
 * Provides Afform + SearchKit artifacts for repeatable Case custom groups.
 *
 * These let a user view/add/edit/delete records of a repeatable (multi-record)
 * Case custom field set on the case screen. They are generated ON THE FLY
 * (never saved), the same way CiviCRM core does:
 *  - the add/edit afforms are contributed via the `civi.afform.get` hook
 *    (mirroring core's CustomGroup::getAfforms::getCustomGroupAfforms, which is
 *    gated behind the civicrm_admin_ui preview — we emit the Case forms without
 *    that gate);
 *  - the SavedSearch + SearchDisplay (the Tab-with-table list) are contributed
 *    via hook_civicrm_managed (mirroring civicrm_admin_ui_civicrm_managed ->
 *    CustomGroup::getSearchKit).
 *
 * Because nothing is persisted, the artifacts always reflect the group's
 * current fields (no re-provisioning) and are discovered by SearchKit exactly
 * like core's (so the row Edit + toolbar Add links resolve). The field *block*
 * (afblockCustom_<name>) is already provided by afform core for any group.
 *
 * We defer entirely to core when civicrm_admin_ui is active (it generates the
 * same, identically-named artifacts) — so nothing is duplicated. See TCOSB-51.
 */
class CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms {

  const CIVICASE_MODULE = 'uk.co.compucorp.civicase';

  /**
   * Base cache key for the repeatable Case custom group list.
   *
   * Domain id is appended (see repeatableGroupsCacheKey) for multidomain
   * safety.
   */
  const REPEATABLE_GROUPS_CACHE_KEY = 'civicase_repeatable_case_custom_groups';

  /**
   * Angular module carrying the add/edit forms' styles.
   */
  const FORM_STYLES_MODULE = 'civicase-repeatable-custom-data-form';

  /**
   * Listener for `civi.afform.get`: contributes the Case create/update afforms.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   The afform.get event; has ->afforms (by-ref), ->getLayout, ->getTypes
   *   and ->getNames.
   */
  public static function getCaseCustomGroupAfforms($event): void {
    if (self::adminUiActive()) {
      return;
    }
    // These are all 'form' afforms; skip if a type filter excludes them.
    if (!empty($event->getTypes) && !in_array('form', $event->getTypes, TRUE)) {
      return;
    }
    $requestedNames = self::requestedAfformNames($event->getNames);
    $service = new self();

    foreach ($service->getRepeatableCaseGroups() as $group) {
      foreach (['create', 'update'] as $action) {
        $prefix = ($action === 'create')
          ? 'afformCreateCustom_'
          : 'afformUpdateCustom_';
        $formName = $prefix . $group['name'];
        // Skip building the layout when a specific afform is
        // requested by name and this is not it.
        if ($requestedNames !== NULL
          && !in_array($formName, $requestedNames, TRUE)) {
          continue;
        }
        $form = $service->buildForm($group, $action, (bool) $event->getLayout);
        $form['has_base'] = TRUE;
        $form['base_module'] = self::CIVICASE_MODULE;
        $event->afforms[$form['name']] = $form;
      }
    }
  }

  /**
   * Listener for `civi.api4.getLinks`: adds Add/Edit links for Case entities.
   *
   * SearchKit then renders the toolbar "Add" button and row "Edit" link,
   * opening the afform popups. Also drops core's contact-hardwired "view" link
   * (which 404s for a Case).
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   Has ->entity (string) and ->links (by-ref array).
   */
  public static function alterCustomEntityLinks($event): void {
    if (self::adminUiActive()) {
      return;
    }
    if (strpos((string) $event->entity, 'Custom_') !== 0) {
      return;
    }
    $groupName = substr($event->entity, strlen('Custom_'));
    $service = new self();
    $groups = array_column($service->getRepeatableCaseGroups(), NULL, 'name');
    if (!isset($groups[$groupName])) {
      return;
    }

    // Drop core's contact-only "view" link (broken for Cases).
    $event->links = array_values(array_filter(
      $event->links,
      fn ($link) => ($link['ui_action'] ?? NULL) !== 'view'
    ));

    // The create form links to the parent case via a TOP-LEVEL `entity_id` URL
    // arg (SearchKit fills the [entity_id] token from the display's filter).
    // This mirrors core's admin_ui (CustomGroupEntityLinks) exactly.
    $event->links[] = [
      'ui_action' => 'add',
      'api_action' => 'create',
      'entity' => $event->entity,
      'path' => 'civicrm/af/custom/' . $groupName . '/create#?entity_id=[entity_id]',
      'text' => ts('Add %1'),
      'icon' => 'fa-plus',
      'weight' => 0,
      'target' => 'crm-popup',
      'conditions' => [],
    ];
    $event->links[] = [
      'ui_action' => 'update',
      'api_action' => 'update',
      'entity' => $event->entity,
      'path' => 'civicrm/af/custom/' . $groupName . '/update#?Record=[id]',
      'text' => ts('Edit %1'),
      'icon' => 'fa-pencil',
      'weight' => 1,
      'target' => 'crm-popup',
      'conditions' => [],
    ];
  }

  /**
   * Managed entities (SavedSearch + SearchDisplay) for hook_civicrm_managed.
   *
   * @return array
   *   List of managed-entity declarations.
   */
  public static function getManagedEntities(): array {
    if (self::adminUiActive()) {
      return [];
    }
    $service = new self();
    $managed = [];
    foreach ($service->getRepeatableCaseGroups() as $group) {
      foreach ($service->searchKitManaged($group) as $entity) {
        $managed[] = $entity;
      }
    }
    return $managed;
  }

  /**
   * Builds the SavedSearch + SearchDisplay managed declarations for a group.
   *
   * Mirrors core's CustomGroup::getSearchKit so the table matches the standard
   * multiple-records display. The row Edit/Delete and "Add" toolbar (target
   * crm-popup on Custom_<name>) auto-wire to the afforms above. The case tab
   * renders the display with a filter of entity_id = caseId.
   *
   * @param array $group
   *   CustomGroup record.
   *
   * @return array
   *   Two managed-entity declarations (SavedSearch, SearchDisplay).
   */
  protected function searchKitManaged(array $group): array {
    $entityName = 'Custom_' . $group['name'];
    $searchName = $entityName . '_Search';
    $displayName = $entityName . '_Tab';

    $groupDetails = \CRM_Core_BAO_CustomGroup::getGroup(['id' => $group['id']]);
    $fields = $groupDetails['fields'] ?? [];
    $displayFields = array_filter($fields, [$this, 'isDisplayedInTable']);

    $select = array_map([$this, 'fieldDisplayKey'], array_values($displayFields));
    foreach ($displayFields as $field) {
      if ($this->fieldIsFile($field)) {
        // The file column links its name to this URL.
        $select[] = $field['name'] . '.url';
      }
    }
    $select[] = 'id';
    $select[] = 'entity_id';

    $columns = array_map([$this, 'searchColumnForField'], array_values($displayFields));
    $columns[] = $this->searchButtonColumn($entityName);

    return [
      [
        'module' => self::CIVICASE_MODULE,
        'name' => 'SavedSearch_' . $searchName,
        'entity' => 'SavedSearch',
        'cleanup' => 'unused',
        'update' => 'unmodified',
        'params' => [
          'version' => 4,
          'values' => [
            'name' => $searchName,
            'label' => ts('%1 Search', [1 => $group['title']]),
            'api_entity' => $entityName,
            'api_params' => [
              'version' => 4,
              'select' => array_values(array_unique($select)),
              'orderBy' => [],
              'where' => [],
            ],
          ],
          'match' => ['name'],
        ],
      ],
      [
        'module' => self::CIVICASE_MODULE,
        'name' => 'SavedSearch_' . $searchName . '_SearchDisplay_' . $displayName,
        'entity' => 'SearchDisplay',
        'cleanup' => 'unused',
        'update' => 'unmodified',
        'params' => [
          'version' => 4,
          'values' => [
            'name' => $displayName,
            'label' => $group['title'],
            'saved_search_id.name' => $searchName,
            'type' => 'table',
            'settings' => [
              'columns' => $columns,
              'sort' => [['id', 'ASC']],
              'pager' => ['show_count' => TRUE, 'expose_limit' => TRUE, 'hide_single' => TRUE],
              'headerCount' => TRUE,
              'actions' => TRUE,
              'classes' => ['table', 'table-striped'],
              'actions_display_mode' => 'menu',
              // "Add" is provided by the case tab's own button (which prefills
              // entity_id from the case); no SearchKit toolbar needed.
            ],
          ],
          'match' => ['saved_search_id', 'name'],
        ],
      ],
    ];
  }

  /**
   * Whether a field is shown as a column in the records table.
   *
   * @param array $field
   *   Custom field record.
   *
   * @return bool
   *   TRUE if the field should be rendered as a table column.
   */
  protected function isDisplayedInTable(array $field): bool {
    return !empty($field['is_active']) && !empty($field['in_selector']);
  }

  /**
   * Builds an inline-editable display column for a custom field.
   *
   * @param array $field
   *   Custom field record.
   *
   * @return array
   *   SearchDisplay column definition.
   */
  protected function searchColumnForField(array $field): array {
    $column = [
      'type' => 'field',
      'key' => $this->fieldDisplayKey($field),
      'label' => $field['label'],
      'sortable' => TRUE,
      // References and files render as read-only joins (the contact's name,
      // the file name), so they cannot be inline-edited here; they are
      // changed via the row Edit form. Option and plain fields stay
      // inline-editable.
      'editable' => !$this->fieldIsReference($field) && !$this->fieldIsFile($field),
    ];
    if ($this->fieldIsFile($field)) {
      $column['link'] = [
        'path' => '[' . $field['name'] . '.url]',
        'target' => '_blank',
      ];
    }

    return $column;
  }

  /**
   * The APIv4 select/column key that renders a field's human-readable value.
   *
   * SearchKit shows the raw stored value unless told otherwise, so:
   *  - option-list / pseudoconstant fields (Select, Radio, Country, State,
   *    Boolean, …) use the "<name>:label" suffix;
   *  - entity-reference fields (e.g. ContactReference) join to the target
   *    entity's label field: "<name>.<labelField>" (e.g. ".display_name");
   *  - plain fields (text, number, date, …) use the field name as-is.
   *
   * @param array $field
   *   Custom field record.
   *
   * @return string
   *   The select expression / column key.
   */
  protected function fieldDisplayKey(array $field): string {
    if ($this->fieldIsFile($field)) {
      return $field['name'] . '.file_name';
    }
    if ($this->fieldIsReference($field)) {
      return $field['name'] . '.' . $this->referenceLabelField($field);
    }
    if ($this->fieldHasOptions($field)) {
      return $field['name'] . ':label';
    }
    return $field['name'];
  }

  /**
   * Whether a field's value is a coded option that has a separate label.
   *
   * @param array $field
   *   Custom field record.
   *
   * @return bool
   *   TRUE for option groups and Country/State/Boolean pseudoconstants.
   */
  protected function fieldHasOptions(array $field): bool {
    if (!empty($field['option_group_id'])) {
      return TRUE;
    }
    return in_array($field['data_type'] ?? '', ['Boolean', 'Country', 'StateProvince'], TRUE);
  }

  /**
   * Whether a field is a file upload.
   *
   * Its stored value is a File id; APIv4 exposes it as the file's (long,
   * tokenised) URL, so the table shows the file name linked to that URL.
   *
   * @param array $field
   *   Custom field record.
   *
   * @return bool
   *   TRUE for File custom fields.
   */
  protected function fieldIsFile(array $field): bool {
    return ($field['data_type'] ?? '') === 'File';
  }

  /**
   * Whether a field is a reference to another entity.
   *
   * Covers both ContactReference (targets a contact) and the generic
   * EntityReference (targets any entity, named in fk_entity) — core treats the
   * two together, so both must render as a label join, not a raw id.
   *
   * @param array $field
   *   Custom field record.
   *
   * @return bool
   *   TRUE if the field stores a reference to another entity.
   */
  protected function fieldIsReference(array $field): bool {
    return in_array($field['data_type'] ?? '', ['ContactReference', 'EntityReference'], TRUE);
  }

  /**
   * The label field on the entity a reference field points at.
   *
   * A ContactReference targets a contact (fk_entity may be empty, so default
   * to Contact); an EntityReference names its target in fk_entity. Either way
   * the target's label field (Contact -> display_name) resolves generically.
   *
   * @param array $field
   *   Custom field record.
   *
   * @return string
   *   The target entity's label field name.
   */
  protected function referenceLabelField(array $field): string {
    $fkEntity = !empty($field['fk_entity']) ? $field['fk_entity'] : 'Contact';
    return CoreUtil::getInfoItem($fkEntity, 'label_field') ?: 'display_name';
  }

  /**
   * Builds the per-row Edit/Delete buttons column.
   *
   * @param string $entityName
   *   The Custom_<name> APIv4 entity.
   *
   * @return array
   *   SearchDisplay buttons column definition.
   */
  protected function searchButtonColumn(string $entityName): array {
    return [
      'type' => 'buttons',
      'alignment' => 'text-right',
      'size' => 'btn-xs',
      'links' => [
        [
          'entity' => $entityName,
          'action' => 'update',
          'target' => 'crm-popup',
          'icon' => 'fa-pencil',
          'text' => ts('Edit'),
          'style' => 'default',
        ],
        [
          'entity' => $entityName,
          'task' => 'delete',
          'target' => 'crm-popup',
          'icon' => 'fa-trash',
          'text' => ts('Delete'),
          'style' => 'danger',
        ],
      ],
    ];
  }

  /**
   * Whether the civicrm_admin_ui preview is active (then core provides these).
   *
   * @return bool
   *   TRUE when AdminUI is active.
   */
  protected static function adminUiActive(): bool {
    return CRM_Extension_System::singleton()
      ->getMapper()
      ->isActiveModule('civicrm_admin_ui');
  }

  /**
   * Narrows an afform.get request to the afform names we care about.
   *
   * @param array|null $getNames
   *   The `getNames` filter from the afform.get event.
   *
   * @return array|null
   *   Explicit list of requested names, or NULL to contribute all (the caller
   *   filters afterwards by module/directive name).
   */
  protected static function requestedAfformNames($getNames): ?array {
    if (empty($getNames['name'])) {
      return NULL;
    }
    return (array) $getNames['name'];
  }

  /**
   * Returns repeatable, Tab-with-table Case custom groups.
   *
   * Cached in the persistent metadata cache. This list is read on every
   * civicase Angular page load (via the getFeaturesSettings settingsFactory,
   * see CRM_Civicase_Settings::setRepeatableCaseCustomGroups) as well as from
   * the afform/managed hooks, so caching avoids repeating the query on hot
   * paths.
   *
   * The cache is invalidated whenever a CustomGroup changes — see
   * CRM_Civicase_Hook_Post_ReconcileRepeatableCaseCustomArtifacts::run(), which
   * clears it at the TOP of the hook, before it reads this list to decide
   * whether to reconcile. So a group created/edited/deleted earlier in the same
   * request is always reflected (the reconcile within that request sees fresh
   * structure). It also clears on a system flush (metadata cache).
   *
   * @return array
   *   List of CustomGroup records.
   */
  public function getRepeatableCaseGroups(): array {
    $cache = \Civi::cache('metadata');
    $key = self::repeatableGroupsCacheKey();
    $cached = $cache->get($key);
    if (is_array($cached)) {
      return $cached;
    }
    $groups = (array) CustomGroup::get(FALSE)
      ->addSelect('id', 'name', 'title', 'extends', 'icon', 'max_multiple')
      ->addWhere('extends', 'IN', self::getCaseExtends())
      ->addWhere('is_multiple', '=', TRUE)
      ->addWhere('style', '=', 'Tab with table')
      ->addWhere('is_active', '=', TRUE)
      ->execute()
      ->getArrayCopy();
    $cache->set($key, $groups);

    return $groups;
  }

  /**
   * Clears the cached repeatable Case custom group list.
   *
   * Called from hook_civicrm_post when a CustomGroup changes so the next read
   * — including the reconcile within that same request — rebuilds from the
   * just-changed structure. See
   * CRM_Civicase_Hook_Post_ReconcileRepeatableCaseCustomArtifacts.
   */
  public static function clearRepeatableCaseGroupsCache(): void {
    \Civi::cache('metadata')->delete(self::repeatableGroupsCacheKey());
  }

  /**
   * Domain-scoped cache key for the repeatable Case custom group list.
   *
   * @return string
   *   The cache key, suffixed with the current domain id.
   */
  private static function repeatableGroupsCacheKey(): string {
    $domainId = \CRM_Core_Config::domainID();
    return self::REPEATABLE_GROUPS_CACHE_KEY . '_' . $domainId;
  }

  /**
   * Reconciles this module's managed SearchKit artifacts.
   *
   * Called when a Case custom group/field changes so the per-group SavedSearch
   * and SearchDisplay are (re)generated immediately — the case tab's table then
   * renders without the admin clearing the cache. Scoped to this module and a
   * no-op when civicrm_admin_ui is active (core manages them).
   */
  public static function reconcileManaged(): void {
    if (self::adminUiActive()) {
      return;
    }
    Managed::reconcile(FALSE)
      ->addModule(self::CIVICASE_MODULE)
      ->execute();

    // Our afforms are contributed via civi.afform.get, but the afform scanner
    // and the Angular module registry both cache the resulting list. Drop them
    // first so the menu rebuild below — and the page render itself — see the
    // new/changed forms; otherwise the popup dies with "Unrecognized Angular
    // module afformCreateCustom<Name>".
    if (function_exists('_afform_clear')) {
      _afform_clear();
    }

    // The add/edit afforms expose server routes (civicrm/af/custom/<name>/*)
    // contributed via civi.afform.get. Those routes are only wired into the
    // router when the menu is (re)built, so rebuild it — otherwise the Add/Edit
    // popups 404 until a manual cache clear.
    CRM_Core_Menu::store();

    // Writing civicrm_menu is not enough on CMSs that keep their own router
    // (Drupal 8+ builds its route collection from CRM_Core_Menu::items() via
    // Drupal\civicrm\Routing\Routes, and only on a router rebuild). Without
    // this the new routes exist Civi-side but the CMS still 404s the Add/Edit
    // popups until someone runs a CMS cache clear. No-op on CMSs that do not
    // cache routes.
    CRM_Core_Config::singleton()->userSystem->invalidateRouteCache();
  }

  /**
   * Case entity values used by `CustomGroup.extends` (base + categories).
   *
   * @return array
   *   Entity values, e.g. ['Case', 'Cases', 'awards', ...].
   */
  public static function getCaseExtends(): array {
    $extends = ['Case'];
    $options = \CRM_Core_BAO_CustomGroup::getCustomGroupExtendsOptions();
    foreach ($options as $option) {
      if (($option['table_name'] ?? NULL) === 'civicrm_case') {
        $extends[] = $option['id'];
      }
    }
    return array_values(array_unique($extends));
  }

  /**
   * Builds an Afform definition for the add or edit form of a group.
   *
   * Same names, routes and entity binding as core's CustomGroup::getAfforms
   * (so SearchKit links and the case tab find them), but with civicase's own
   * layout (see buildLayout) so the form matches the case screens.
   *
   * @param array $group
   *   CustomGroup record.
   * @param string $action
   *   Either 'create' or 'update'.
   * @param bool $withLayout
   *   Whether to render the form layout.
   *
   * @return array
   *   Afform definition.
   */
  public function buildForm(array $group, string $action, bool $withLayout = TRUE): array {
    $name = $group['name'];
    $isCreate = ($action === 'create');

    $form = [
      'name' => ($isCreate ? 'afformCreateCustom_' : 'afformUpdateCustom_') . $name,
      'type' => 'form',
      'title' => $isCreate
        ? ts('Add %1', [1 => $group['title']])
        : ts('Update %1', [1 => $group['title']]),
      'description' => '',
      'is_public' => FALSE,
      // Edit access is per group ("access all custom data" or an ACL "Edit"
      // grant), which an afform permission cannot express; saving is refused
      // by CRM_Civicase_Event_Listener_RepeatableCaseCustomDataAccess.
      'permission' => ['access CiviCRM'],
      'server_route' => 'civicrm/af/custom/' . $name . '/' . $action,
      'icon' => $group['icon'] ?? NULL,
      // Pulls in the form's styles (standalone page and crm-popup alike).
      'requires' => [self::FORM_STYLES_MODULE],
    ];

    if ($withLayout) {
      $details = \CRM_Core_BAO_CustomGroup::getGroup(['id' => $group['id']]) ?? [];
      $form['layout'] = $this->buildLayout($group + $details, $details['fields'] ?? [], $isCreate);
    }

    return $form;
  }

  /**
   * Builds the add/edit form markup.
   *
   * Equivalent to core's afformEdit.tpl + afblock.tpl (entity, hidden parent
   * link, group help, one af-field per active field, Save), with these
   * changes:
   *  - each field is labelled with its own label. APIv4 labels Custom_<group>
   *    fields "<Group>: <Field>", which is redundant on a form for one group
   *    and differs from the case tab's column headers;
   *  - fields set as "Required" are marked required. APIv4 reports custom
   *    fields as not required, so without this afform neither shows the
   *    asterisk nor validates them (client side or on submit);
   *  - a styling hook class and a footer around the Save button;
   *  - Save validates first and says which fields are wrong and why (see
   *    civicaseRepeatableCustomDataSubmit), instead of afform's generic
   *    "Please fill all required fields." for any invalid input.
   *
   * @param array $group
   *   CustomGroup record (name, title, extends, help_pre, help_post).
   * @param array $fields
   *   The group's custom fields, in display order.
   * @param bool $isCreate
   *   TRUE for the add form, FALSE for the edit form.
   *
   * @return string
   *   Afform layout HTML.
   */
  public function buildLayout(array $group, array $fields, bool $isCreate): string {
    $entity = $this->layoutEntity($group);
    $attr = fn ($value) => htmlspecialchars(
      is_string($value) ? $value : json_encode($value),
      ENT_QUOTES
    );

    $html = '<af-form ctrl="afform" class="civicase__repeatable-custom-data-form">';
    $html .= sprintf(
      '<af-entity type="%s" name="%s" label="%s" actions="%s" security="RBAC" url-autofill="%s" />',
      $attr($entity['type']),
      $attr($entity['name']),
      $attr($entity['label']),
      $attr(['create' => $isCreate, 'update' => !$isCreate]),
      $isCreate ? '' : '1'
    );
    $html .= sprintf('<fieldset af-fieldset="%s" class="af-container">', $attr($entity['name']));
    $html .= sprintf(
      '<af-field name="%s" defn="%s" />',
      $attr($entity['parent_field']),
      $attr($entity['parent_field_defn'])
    );

    if (!empty($group['help_pre'])) {
      $html .= '<div class="af-markup">' . $group['help_pre'] . '</div>';
    }
    foreach ($fields as $field) {
      if (empty($field['is_active'])) {
        continue;
      }
      $defn = ['label' => $field['label']];
      if (!empty($field['is_required'])) {
        $defn['required'] = TRUE;
      }
      $html .= sprintf(
        '<af-field name="%s" defn="%s" />',
        $attr($field['name']),
        $attr($defn)
      );
    }
    if (!empty($group['help_post'])) {
      $html .= '<div class="af-markup">' . $group['help_post'] . '</div>';
    }

    $html .= '</fieldset>';
    $html .= '<div class="civicase__repeatable-custom-data-form__actions">';
    $html .= sprintf(
      '<button type="button" class="af-button btn btn-primary" crm-icon="fa-save" civicase-repeatable-custom-data-submit="afform">%s</button>',
      htmlspecialchars(ts('Save'), ENT_QUOTES)
    );
    $html .= '</div></af-form>';

    return $html;
  }

  /**
   * The afform layout entity that binds the form to the Case custom record.
   *
   * The record entity is `Custom_<name>` and its parent is the case, linked
   * through the `entity_id` field rendered as a hidden input.
   *
   * @param array $group
   *   CustomGroup record.
   *
   * @return array
   *   The afform `af-entity` definition.
   */
  public function layoutEntity(array $group): array {
    return [
      'type' => 'Custom_' . $group['name'],
      'name' => 'Record',
      'label' => $group['extends'] . ' ' . $group['title'],
      'parent_field' => 'entity_id',
      'parent_field_defn' => [
        'input_type' => 'Hidden',
        'label' => FALSE,
      ],
    ];
  }

}
