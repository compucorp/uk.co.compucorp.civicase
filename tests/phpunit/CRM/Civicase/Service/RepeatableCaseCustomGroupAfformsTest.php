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
   * Option, pseudoconstant and reference fields resolve to readable keys.
   *
   * Option groups and the Country/State/Boolean pseudoconstants use the
   * ":label" suffix; a ContactReference joins to the target's label field
   * (display_name); plain fields are unchanged. This is what makes the case
   * tab render labels instead of coded values / contact ids.
   */
  public function testFieldDisplayKeyResolvesLabelsAndReferences() {
    $service = new Service();
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
  }

}
