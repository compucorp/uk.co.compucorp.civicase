/**
 * TCOSB-23 — Configure Repeatable Custom Field Sets for Cases (CiviCRM Admin).
 *
 * Verifies that, once civicase enables multi-record support for Cases, the core
 * Custom Group admin form exposes the repeatable-set controls for a Case and
 * that the configuration persists.
 *
 *  - AC1: the "Allow multiple records" checkbox, "Maximum records" field and the
 *         "Tab with table" display style are available for a Case custom group.
 *  - AC2: enabling them and saving persists the configuration.
 */

import { test, expect } from '../helpers/fixtures';
import { civiLogin } from '../helpers/civicrm-login';

// A Case category as shown in the "Used For" (extends) select2. Its option
// value is 'Cases', which is the id in the form's `allowMultiple` map.
const CASE_USED_FOR_LABEL = 'Case (Cases)';
const CASE_EXTENDS_VALUE = 'Cases';
const GROUP_TITLE = 'ZZ E2E Repeatable Case';

test.beforeEach(async ({ civi }) => {
  await civi.deleteCustomGroupByTitle(GROUP_TITLE);
});

test.afterEach(async ({ civi }) => {
  await civi.deleteCustomGroupByTitle(GROUP_TITLE);
});

test('Case custom group exposes and saves repeatable-set configuration', async ({ page, civi }) => {
  await civiLogin(page);

  // Open the "new custom field set" admin form.
  await page.goto('/civicrm/admin/custom/group/edit?action=add&reset=1');
  await page.locator('input[name="title"]').waitFor({ state: 'visible', timeout: 20_000 });

  // Set "Used For" to a Case category by driving the real select2 widget:
  // open the dropdown, filter, and pick the option (this fires the change the
  // form listens to, which reveals the multi-record rows).
  await page.locator('#s2id_extends').click();
  await page.locator('#select2-drop input.select2-input').fill(CASE_USED_FOR_LABEL);
  await page.locator('#select2-drop .select2-results li', { hasText: CASE_USED_FOR_LABEL }).first().click();

  // AC1 — the repeatable-set controls become available for a Case.
  const isMultiple = page.locator('#is_multiple');
  await expect(page.locator('tr.field-is_multiple')).toBeVisible();

  await isMultiple.check();

  await expect(page.locator('tr.field-max_multiple')).toBeVisible();
  await expect(page.locator('select[name="style"] option[value="Tab with table"]')).toHaveCount(1);

  // Configure a repeatable "Tab with table" set and save.
  await page.locator('input[name="title"]').fill(GROUP_TITLE);
  await page.locator('input[name="max_multiple"]').fill('3');
  await page.selectOption('select[name="style"]', 'Tab with table');
  await page.locator('button[name="_qf_Group_next-bottom"], button.crm-button:has-text("Save")').first().click();

  // The form redirects to the "add fields" screen on success.
  await expect(page).toHaveURL(/civicrm\/admin\/custom\/group\/field/, { timeout: 20_000 });

  // AC2 (persistence) — the configuration is stored.
  const saved = civi.values(
    await civi.api3('CustomGroup', 'get', {
      sequential: 1,
      title: GROUP_TITLE,
      return: 'id,extends,is_multiple,max_multiple,style',
    }),
  );
  expect(saved).toHaveLength(1);
  // The group is attached to a Case entity (base 'Case' or a case category).
  expect(['Case', 'Cases', 'awards', 'Prospecting', 'applicant_managementType'])
    .toContain(String(saved[0].extends));
  expect(Number(saved[0].is_multiple)).toBe(1);
  expect(Number(saved[0].max_multiple)).toBe(3);
  expect(String(saved[0].style)).toBe('Tab with table');

  // AC2 ("available on subsequent edits") — reopen the edit form and confirm
  // the repeatable configuration is reflected back to the admin.
  await page.goto(`/civicrm/admin/custom/group/edit?action=update&reset=1&id=${saved[0].id}`);
  await expect(page.locator('#is_multiple')).toBeChecked();
  await expect(page.locator('input[name="max_multiple"]')).toHaveValue('3');
  await expect(page.locator('select[name="style"]')).toHaveValue('Tab with table');
});
