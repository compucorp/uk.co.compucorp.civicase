<?php

use Civi\Api4\CustomGroup;
use Civi\Api4\Managed;

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
        // Skip building (which renders Smarty) when a specific afform is
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
    $displayFields = array_filter($fields, fn ($f) => !empty($f['is_active']));

    $select = array_column($fields, 'name');
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
   * Builds an inline-editable display column for a custom field.
   *
   * @param array $field
   *   Custom field record.
   *
   * @return array
   *   SearchDisplay column definition.
   */
  protected function searchColumnForField(array $field): array {
    return [
      'type' => 'field',
      'key' => $field['name'],
      'label' => $field['label'],
      'sortable' => TRUE,
      'editable' => TRUE,
    ];
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
   * @return array
   *   List of CustomGroup records.
   */
  public function getRepeatableCaseGroups(): array {
    // Intentionally NOT statically cached: reconcileManaged() runs from
    // hook_civicrm_post right after a CustomGroup/CustomField is saved and must
    // see the just-changed structure within the same request. A per-request
    // cache would hide a group created earlier in that request. The query is a
    // single cheap APIv4 read.
    return (array) CustomGroup::get(FALSE)
      ->addSelect('id', 'name', 'title', 'extends', 'icon', 'max_multiple')
      ->addWhere('extends', 'IN', $this->getCaseExtends())
      ->addWhere('is_multiple', '=', TRUE)
      ->addWhere('style', '=', 'Tab with table')
      ->addWhere('is_active', '=', TRUE)
      ->execute()
      ->getArrayCopy();
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

    // The add/edit afforms expose server routes (civicrm/af/custom/<name>/*)
    // contributed via civi.afform.get. Those routes are only wired into the
    // router when the menu is (re)built, so rebuild it — otherwise the Add/Edit
    // popups 404 until a manual cache clear.
    CRM_Core_Menu::store();
  }

  /**
   * Case entity values used by `CustomGroup.extends` (base + categories).
   *
   * @return array
   *   Entity values, e.g. ['Case', 'Cases', 'awards', ...].
   */
  protected function getCaseExtends(): array {
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
   * Mirrors core's CustomGroup::getAfforms (generate create/update form) so the
   * artifact is identical to core's.
   *
   * @param array $group
   *   CustomGroup record.
   * @param string $action
   *   Either 'create' or 'update'.
   * @param bool $withLayout
   *   Whether to render the (expensive) form layout.
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
      'permission' => ['access CiviCRM'],
      'server_route' => 'civicrm/af/custom/' . $name . '/' . $action,
      'icon' => $group['icon'] ?? NULL,
    ];

    if ($withLayout) {
      $form['layout'] = \CRM_Core_Smarty::singleton()->fetchWith(
        'afform/customGroups/afformEdit.tpl',
        [
          'formEntity' => $this->layoutEntity($group),
          'formActions' => [
            'create' => $isCreate,
            'update' => !$isCreate,
          ],
          'urlAutofill' => !$isCreate,
          'blockDirective' => _afform_angular_module_name('afblockCustom_' . $name, 'dash'),
        ]
      );
    }

    return $form;
  }

  /**
   * The afform layout entity that binds the form to the Case custom record.
   *
   * This is our contribution to the generated form (core's Smarty template
   * renders around it): the record entity is `Custom_<name>` and its parent is
   * the case, linked through the `entity_id` field rendered as a hidden input.
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
