<?php

use Civi\Core\Event\GenericHookEvent;
use CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms as Service;

/**
 * Tests the afform/SearchKit generation for repeatable Case custom groups.
 *
 * These cover the pure generation logic + the getLinks guard. The
 * group-driven hook behaviour (civi.afform.get / hook_civicrm_managed
 * contributions for real groups) is exercised by the Playwright E2E, since
 * creating a custom group in a transactional headless test would leak its
 * DDL table.
 *
 * @group headless
 */
class CRM_Civicase_Service_RepeatableCaseCustomGroupAfformsTest extends BaseHeadlessTest {

  /**
   * A fake repeatable Case custom group (no DB row needed by buildForm).
   *
   * @return array
   *   CustomGroup-shaped array.
   */
  private function group(): array {
    return [
      'id' => 0,
      'name' => 'Publications',
      'title' => 'Publications',
      'extends' => 'Case',
      'icon' => NULL,
      'max_multiple' => NULL,
    ];
  }

  /**
   * The create afform definition has the core-compatible name and route.
   */
  public function testBuildFormCreateDefinition() {
    $form = (new Service())->buildForm($this->group(), 'create', FALSE);

    $this->assertEquals('afformCreateCustom_Publications', $form['name']);
    $this->assertEquals('form', $form['type']);
    $this->assertEquals('civicrm/af/custom/Publications/create', $form['server_route']);
    // No layout requested -> not generated (perf).
    $this->assertArrayNotHasKey('layout', $form);
  }

  /**
   * Add/edit afforms only require CiviCRM access.
   *
   * Edit access is per group ("access all custom data" or an ACL "Edit"
   * grant), which an afform permission cannot express; the API refuses the
   * save instead.
   */
  public function testBuildFormRequiresOnlyCiviCrmAccess() {
    foreach (['create', 'update'] as $action) {
      $form = (new Service())->buildForm($this->group(), $action, FALSE);

      $this->assertEquals(['access CiviCRM'], $form['permission']);
    }
  }

  /**
   * Only active fields with "Display in table?" ticked become table columns.
   */
  public function testIsDisplayedInTableRespectsInSelector() {
    $service = new Service();
    $method = new ReflectionMethod(Service::class, 'isDisplayedInTable');
    $method->setAccessible(TRUE);
    $shown = fn (array $field) => $method->invoke($service, $field);

    $this->assertTrue($shown(['is_active' => 1, 'in_selector' => 1]));
    $this->assertFalse($shown(['is_active' => 1, 'in_selector' => 0]));
    $this->assertFalse($shown(['is_active' => 0, 'in_selector' => 1]));
    $this->assertFalse($shown(['is_active' => 1]));
  }

  /**
   * Row Edit/Delete buttons carry no fixed permission condition.
   *
   * Access is per group and per user (ACL), so SearchKit's own API access
   * check decides the Edit link and the tab hides Delete client-side.
   */
  public function testButtonColumnLinksHaveNoPermissionCondition() {
    $method = new ReflectionMethod(Service::class, 'searchButtonColumn');
    $method->setAccessible(TRUE);
    $column = $method->invoke(new Service(), 'Custom_Publications');

    $this->assertCount(2, $column['links']);
    foreach ($column['links'] as $link) {
      $this->assertArrayNotHasKey('conditions', $link);
    }
  }

  /**
   * The forms pull in the module that carries their styles.
   */
  public function testBuildFormRequiresStylesModule() {
    $form = (new Service())->buildForm($this->group(), 'create', FALSE);

    $this->assertEquals(['civicase-repeatable-custom-data-form'], $form['requires']);
  }

  /**
   * The layout labels each active field with its own label (no group prefix).
   */
  public function testBuildLayoutUsesFieldLabelsAndSkipsInactiveFields() {
    $fields = [
      ['name' => 'title_field', 'label' => "Author's name", 'is_active' => 1],
      ['name' => 'old_field', 'label' => 'Old', 'is_active' => 0],
    ];
    $group = $this->group() + ['help_pre' => '<p>Before</p>', 'help_post' => ''];

    $layout = (new Service())->buildLayout($group, $fields, TRUE);

    $this->assertStringContainsString('class="civicase__repeatable-custom-data-form"', $layout);
    $this->assertStringContainsString('<af-field name="title_field" defn="{&quot;label&quot;:&quot;Author&#039;s name&quot;}" />', $layout);
    $this->assertStringNotContainsString('old_field', $layout);
    $this->assertStringContainsString('<div class="af-markup"><p>Before</p></div>', $layout);
    $this->assertStringContainsString('url-autofill=""', $layout);
    $this->assertStringContainsString('civicase__repeatable-custom-data-form__actions', $layout);
    $this->assertStringContainsString('civicase-repeatable-custom-data-submit="afform"', $layout);
  }

  /**
   * Fields set as "Required" are required on the form; others are not.
   */
  public function testBuildLayoutMarksRequiredFields() {
    $fields = [
      ['name' => 'needed', 'label' => 'Needed', 'is_active' => 1, 'is_required' => 1],
      ['name' => 'optional', 'label' => 'Optional', 'is_active' => 1, 'is_required' => 0],
    ];

    $layout = (new Service())->buildLayout($this->group(), $fields, TRUE);

    $this->assertStringContainsString('<af-field name="needed" defn="{&quot;label&quot;:&quot;Needed&quot;,&quot;required&quot;:true}" />', $layout);
    $this->assertStringContainsString('<af-field name="optional" defn="{&quot;label&quot;:&quot;Optional&quot;}" />', $layout);
  }

  /**
   * The edit form autofills the record from the URL; the add form does not.
   */
  public function testBuildLayoutUpdateAutofillsFromUrl() {
    $layout = (new Service())->buildLayout($this->group(), [], FALSE);

    $this->assertStringContainsString('url-autofill="1"', $layout);
    $this->assertStringContainsString('actions="{&quot;create&quot;:false,&quot;update&quot;:true}"', $layout);
  }

  /**
   * The update afform definition has the core-compatible name and route.
   */
  public function testBuildFormUpdateDefinition() {
    $form = (new Service())->buildForm($this->group(), 'update', FALSE);

    $this->assertEquals('afformUpdateCustom_Publications', $form['name']);
    $this->assertEquals('civicrm/af/custom/Publications/update', $form['server_route']);
  }

  /**
   * The layout entity binds the form to the Case custom record via entity_id.
   *
   * This is our contribution to the generated form; the surrounding Smarty
   * render (core's template) is exercised end-to-end by the Playwright E2E.
   */
  public function testLayoutEntityBindsToCaseCustomRecord() {
    $entity = (new Service())->layoutEntity($this->group());

    $this->assertEquals('Custom_Publications', $entity['type']);
    $this->assertEquals('entity_id', $entity['parent_field']);
    $this->assertEquals('Hidden', $entity['parent_field_defn']['input_type']);
  }

  /**
   * Ignores entities that are not repeatable Case groups (getLinks guard).
   */
  public function testAlterCustomEntityLinksIgnoresNonCustomEntity() {
    $links = [['ui_action' => 'view', 'entity' => 'Contact']];
    $event = GenericHookEvent::create(['entity' => 'Contact', 'links' => &$links]);

    Service::alterCustomEntityLinks($event);

    $this->assertCount(1, $links);
    $this->assertEquals('view', $links[0]['ui_action']);
  }

  /**
   * Ignores Custom_ entities that are not Case groups (getLinks guard).
   */
  public function testAlterCustomEntityLinksIgnoresUnknownCustomEntity() {
    $links = [['ui_action' => 'view', 'entity' => 'Custom_NotACaseGroup']];
    $event = GenericHookEvent::create(['entity' => 'Custom_NotACaseGroup', 'links' => &$links]);

    Service::alterCustomEntityLinks($event);

    $this->assertCount(1, $links);
    $this->assertEquals('view', $links[0]['ui_action']);
  }

  /**
   * A file column shows the file name, linked to the file, and is read-only.
   *
   * Where core joins File fields to their file, the column reads the field's
   * own join.
   */
  public function testFileColumnLinksFileName() {
    $method = new ReflectionMethod(Service::class, 'searchColumnForField');
    $method->setAccessible(TRUE);
    $field = ['name' => 'a_file', 'label' => 'Attachment', 'data_type' => 'File'];
    $column = $method->invoke($this->serviceWhereCoreJoinsFiles(TRUE), $field);

    $this->assertEquals('a_file.file_name', $column['key']);
    $this->assertEquals(['path' => '[a_file.url]', 'target' => '_blank'], $column['link']);
    $this->assertFalse($column['editable']);
  }

  /**
   * Where core does not join File fields, the column reads the explicit join.
   */
  public function testFileColumnReadsExplicitJoinWhenCoreDoesNotJoinFiles() {
    $method = new ReflectionMethod(Service::class, 'searchColumnForField');
    $method->setAccessible(TRUE);
    $field = ['name' => 'a_file', 'label' => 'Attachment', 'data_type' => 'File'];
    $column = $method->invoke($this->serviceWhereCoreJoinsFiles(FALSE), $field);

    $this->assertEquals('record_file_a_file.file_name', $column['key']);
    $this->assertEquals(['path' => '[record_file_a_file.url]', 'target' => '_blank'], $column['link']);
    $this->assertFalse($column['editable']);
  }

  /**
   * Files are joined from the record's Case, once per File field.
   */
  public function testFileJoinsGoThroughTheCase() {
    $method = new ReflectionMethod(Service::class, 'fileJoins');
    $method->setAccessible(TRUE);
    $fields = [
      ['name' => 'a_file', 'data_type' => 'File'],
      ['name' => 'b_file', 'data_type' => 'File'],
    ];

    $joins = $method->invoke($this->serviceWhereCoreJoinsFiles(FALSE), $fields, 'civicrm_value_pubs_9');

    $this->assertEquals([
      ['Case AS record_case', 'LEFT', ['record_case.id', '=', 'entity_id']],
      [
        'File AS record_file_a_file',
        'LEFT',
        'EntityFile',
        ['record_case.id', '=', 'record_file_a_file.entity_id'],
        ['record_file_a_file.entity_table', '=', "'civicrm_value_pubs_9'"],
        ['record_file_a_file.id', '=', 'a_file'],
      ],
      [
        'File AS record_file_b_file',
        'LEFT',
        'EntityFile',
        ['record_case.id', '=', 'record_file_b_file.entity_id'],
        ['record_file_b_file.entity_table', '=', "'civicrm_value_pubs_9'"],
        ['record_file_b_file.id', '=', 'b_file'],
      ],
    ], $joins);
    $this->assertSame([], $method->invoke($this->serviceWhereCoreJoinsFiles(TRUE), $fields, 'civicrm_value_pubs_9'));
    $this->assertSame([], $method->invoke($this->serviceWhereCoreJoinsFiles(FALSE), [], 'civicrm_value_pubs_9'));
  }

  /**
   * Option, pseudoconstant and reference fields resolve to readable keys.
   */
  public function testFieldDisplayKeyResolvesLabelsAndReferences() {
    $service = $this->serviceWhereCoreJoinsFiles(TRUE);
    $method = new ReflectionMethod(Service::class, 'fieldDisplayKey');
    $method->setAccessible(TRUE);
    $key = fn (array $field) => $method->invoke($service, $field);

    $this->assertEquals('plain_text', $key(['name' => 'plain_text', 'data_type' => 'String', 'html_type' => 'Text']));
    $this->assertEquals('a_select:label', $key(['name' => 'a_select', 'data_type' => 'String', 'option_group_id' => 7]));
    $this->assertEquals('a_country:label', $key(['name' => 'a_country', 'data_type' => 'Country']));
    $this->assertEquals('a_bool:label', $key(['name' => 'a_bool', 'data_type' => 'Boolean']));
    $this->assertEquals('a_contact.display_name', $key(['name' => 'a_contact', 'data_type' => 'ContactReference']));
    $entityRef = ['name' => 'an_entity', 'data_type' => 'EntityReference', 'fk_entity' => 'Contact'];
    $this->assertEquals('an_entity.display_name', $key($entityRef));
    $this->assertEquals('a_file.file_name', $key(['name' => 'a_file', 'data_type' => 'File']));
  }

  /**
   * A group's case types are read from every shape the value comes in.
   */
  public function testGetGroupCaseTypeIds() {
    $sep = CRM_Core_DAO::VALUE_SEPARATOR;
    $ids = fn ($value) => Service::getGroupCaseTypeIds(['extends_entity_column_value' => $value]);

    $this->assertEquals([10], $ids(['10']));
    $this->assertEquals([4, 7], $ids(['4', 7, '4']));
    $this->assertEquals([4, 7], $ids($sep . '4' . $sep . '7' . $sep));
    $this->assertNull($ids(NULL));
    $this->assertNull($ids([]));
    $this->assertNull($ids(''));
    $this->assertNull(Service::getGroupCaseTypeIds([]));
    $this->assertEquals([], $ids('null'));
  }

  /**
   * The service, as on a core that does or does not join File fields.
   *
   * @param bool $coreJoinsFiles
   *   Whether core joins File custom fields to their file.
   *
   * @return \CRM_Civicase_Service_RepeatableCaseCustomGroupAfforms
   *   The service.
   */
  private function serviceWhereCoreJoinsFiles(bool $coreJoinsFiles): Service {
    return new class($coreJoinsFiles) extends Service {

      /**
       * Whether core joins File custom fields to their file.
       *
       * @var bool
       */
      private $coreJoinsFiles;

      /**
       * Sets whether core joins File custom fields to their file.
       *
       * @param bool $coreJoinsFiles
       *   Whether core joins File custom fields to their file.
       */
      public function __construct(bool $coreJoinsFiles) {
        $this->coreJoinsFiles = $coreJoinsFiles;
      }

      /**
       * {@inheritdoc}
       */
      protected function coreJoinsFileFields(): bool {
        return $this->coreJoinsFiles;
      }

    };
  }

}
