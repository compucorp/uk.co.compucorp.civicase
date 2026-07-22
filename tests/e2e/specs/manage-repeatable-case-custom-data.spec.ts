/**
 * TCOSB-51 — 1.2 Manage Repeatable Case Custom Data (CiviCRM User).
 *
 * Drives the runtime a CiviCRM user gets on the case screen for a repeatable
 * ("Tab with table") Case custom group: a dedicated tab with a SearchKit table
 * of the case's records, plus Add / Edit / Delete via the generated afforms and
 * the max-records limit.
 *
 *  - AC1: add a new record (linked to the case via entity_id).
 *  - AC2: the "Add" button is hidden once the max is reached (not bypassable).
 *  - AC5: all of the case's records are listed in the tab table.
 *  - AC6: edit a single record.
 *  - AC7: delete a single record.
 *  - AC8: changes persist (verified by reloading the table from the server).
 *
 * Self-contained: creates its own repeatable Case group + field + case over the
 * API and tears them down, so it does not depend on pre-seeded fixtures.
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../helpers/fixtures';
import { civiLogin } from '../helpers/civicrm-login';
import { CiviCrmApi } from '../helpers/civicrm-api';

const GROUP_TITLE = 'ZZ E2E Repeat Case Data';
const FIELD_LABEL = 'E2E Entry Name';
const MAX_RECORDS = 2;

// Resolved dynamically in beforeAll (no hardcoded, environment-specific IDs):
// an active Case Type, the category it belongs to (the civicase app opens under
// that category), and a throwaway client contact.
let caseTypeId = 0;
let caseCategory = 0;
let contactId = 0;

let groupName = '';
let fieldName = '';
let caseId = 0;

/**
 * Wait for the civicase Angular case screen to have rendered the custom-data
 * tab for our group, then open it.
 */
async function openGroupTab(page: Page): Promise<void> {
  const tab = page.locator('a', { hasText: GROUP_TITLE }).first();
  await tab.waitFor({ state: 'visible', timeout: 30_000 });
  await tab.click();
  // The tab hosts our directive wrapper.
  await page.locator('form.civicase__case-custom-data-tab').waitFor({ state: 'visible', timeout: 20_000 });
}

/** Count rows currently shown in the tab's SearchKit table. */
function rows(page: Page) {
  return page.locator('crm-search-display-table table tbody tr');
}

/** The tab's own "Add" button (hidden when max reached or no create access). */
function addButton(page: Page) {
  return page.locator('.civicase__case-custom-data-tab__add');
}

/** Fill the single text field in the open afform dialog and save. */
async function fillAndSave(page: Page, value: string): Promise<void> {
  const dialog = page.locator('.ui-dialog:visible').last();
  const field = dialog.locator('input[type=text]').first();
  await field.waitFor({ state: 'visible', timeout: 15_000 });
  // The afform submits its Angular model, and prefills asynchronously (Edit loads
  // the record, blocking the form meanwhile). Wait for that block overlay to
  // clear so the model is bound before we type — otherwise the fill either fails
  // to bind or gets overwritten by a late prefill.
  await dialog.locator('.blockUI, .blockOverlay').first().waitFor({ state: 'hidden', timeout: 15_000 }).catch(() => {});
  // The afform submits its Angular model and prefills asynchronously (Edit loads
  // the record after the overlay clears). Type, confirm the value bound, then
  // re-confirm after a beat so a late prefill that overwrites our input is
  // caught and the whole fill retried. This double-check is load-bearing — the
  // Edit flow fails intermittently without it.
  await expect(async () => {
    await field.fill(value);
    await expect(field).toHaveValue(value, { timeout: 2_000 });
    await page.waitForTimeout(500);
    await expect(field).toHaveValue(value, { timeout: 2_000 });
  }).toPass({ timeout: 20_000 });
  await dialog.locator('button:has-text("Save")').first().click();
  // Dialog closes on a successful save.
  await expect(page.locator('.ui-dialog:visible')).toHaveCount(0, { timeout: 20_000 });
}

test.beforeAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  await civi.deleteCustomGroupByTitle(GROUP_TITLE);

  // Repeatable "Tab with table" Case custom group + one text field.
  const group = civi.values(
    await civi.api3('CustomGroup', 'create', {
      title: GROUP_TITLE,
      extends: 'Case',
      is_multiple: 1,
      max_multiple: MAX_RECORDS,
      style: 'Tab with table',
      collapse_display: 0,
    }),
  )[0];
  groupName = String(group.name);

  const field = civi.values(
    await civi.api3('CustomField', 'create', {
      custom_group_id: group.id,
      label: FIELD_LABEL,
      data_type: 'String',
      html_type: 'Text',
      is_active: 1,
      is_searchable: 1,
    }),
  )[0];
  fieldName = String(field.name);

  // NB: deliberately NO System.flush here. Creating the group + field must
  // provision the managed SavedSearch/SearchDisplay on its own (via the
  // hook_civicrm_post reconcile), so the tab works without an admin clearing
  // the cache. Flushing here would mask a regression of that behaviour.

  // An active Case Type + its category (the civicase app opens under it) and a
  // throwaway contact — resolved dynamically so this runs on any site.
  const caseType = civi.values(await civi.api3('CaseType', 'get', {
    is_active: 1, options: { limit: 1, sort: 'id ASC' },
  }))[0];
  caseTypeId = Number(caseType.id);
  caseCategory = Number(caseType.case_type_category);
  contactId = Number(civi.values(await civi.api3('Contact', 'create', {
    contact_type: 'Individual', first_name: 'E2E', last_name: 'Manage Client',
  }))[0].id);

  // A case to attach the records to.
  const kase = civi.values(
    await civi.api3('Case', 'create', {
      case_type_id: caseTypeId,
      contact_id: contactId,
      creator_id: contactId,
      subject: 'E2E Repeatable Custom Data',
      status_id: 'Open',
    }),
  )[0];
  caseId = Number(kase.id);
});

test.afterAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  if (caseId) {
    await civi.api3('Case', 'delete', { id: caseId }).catch(() => {});
  }
  await civi.deleteCustomGroupByTitle(GROUP_TITLE).catch(() => {});
  if (contactId) await civi.api3('Contact', 'delete', { id: contactId, skip_undelete: 1 }).catch(() => {});
});

test('add, edit, delete and max-limit on a repeatable Case custom-data tab', async ({ page }) => {
  await civiLogin(page);
  await page.goto(`/civicrm/case/a/?case_type_category=${caseCategory}#/case/list?caseId=${caseId}`);

  await openGroupTab(page);

  // Starts empty (AC5).
  await expect(rows(page)).toHaveCount(0);
  await expect(addButton(page)).toBeVisible();

  // AC1 — add a first record; it appears in the case's (entity_id-filtered) table.
  await addButton(page).click();
  await fillAndSave(page, 'Alpha entry');
  await expect(rows(page)).toHaveCount(1, { timeout: 20_000 });
  await expect(rows(page).first()).toContainText('Alpha entry');

  // AC6 — edit that record; the table reflects the change.
  await rows(page).first().locator('a:has-text("Edit"), a[title*="Edit"]').first().click();
  await fillAndSave(page, 'Alpha edited');
  await expect(rows(page).first()).toContainText('Alpha edited', { timeout: 20_000 });

  // AC1 (2nd) — add up to the max.
  await addButton(page).click();
  await fillAndSave(page, 'Beta entry');
  await expect(rows(page)).toHaveCount(2, { timeout: 20_000 });

  // AC2 — max reached: the Add button is no longer offered.
  await expect(addButton(page)).toHaveCount(0);

  // AC7 — delete one; Add becomes available again and the row is gone.
  await rows(page).filter({ hasText: 'Beta entry' })
    .locator('a:has-text("Delete"), a[title*="Delete"]').first().click();
  const confirm = page.locator('.ui-dialog:visible button:has-text("Delete"), .ui-dialog:visible button:has-text("Continue")').last();
  await confirm.waitFor({ state: 'visible', timeout: 15_000 });
  await confirm.click();
  await expect(rows(page)).toHaveCount(1, { timeout: 20_000 });
  await expect(addButton(page)).toBeVisible();

  // AC8 — persistence: reload the page and the surviving record is still there.
  await page.reload();
  await openGroupTab(page);
  await expect(rows(page)).toHaveCount(1, { timeout: 20_000 });
  await expect(rows(page).first()).toContainText('Alpha edited');
});
