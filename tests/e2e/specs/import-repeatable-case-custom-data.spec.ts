/**
 * TCOSB-64 — 1.8 CiviCRM Imports: Support Repeatable Case Custom Field Sets.
 *
 * Core's Import framework has no additive path for repeatable Case custom data,
 * so 1.8 is delivered additively via nz.co.fuzion.csvimport ("CSV GUI Import to
 * api", installed) calling a thin civicase API endpoint, CaseCustomImporter.create,
 * once per CSV row (same pattern as uk.co.compucorp.membershipextrasimporterapi).
 * The parent Case is matched by Case ID; each row creates NEW record(s)
 * (create-only — AC2/AC3, never overwrites).
 *
 * This spec drives CaseCustomImporter.create directly (the exact call csvimport
 * makes per row) in the authenticated session — a real regression guard without
 * the brittle csvimport QuickForm UI (that round-trip is covered by manual QA),
 * and without the DDL-leak that blocks creating a real custom group in a headless
 * PHPUnit test.
 *  - AC1: the repeatable group's fields are offered as importable columns.
 *  - AC2/AC3: two rows create two separate records against the same Case.
 *  - AC5: an invalid row (unknown Case) is rejected with an error.
 *
 * Self-contained: creates its own group + field + Case + records (no hardcoded
 * IDs) and tears them all down.
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../helpers/fixtures';
import { civiLogin } from '../helpers/civicrm-login';
import { CiviCrmApi } from '../helpers/civicrm-api';

const GROUP_TITLE = 'ZZ E2E Import Repeat Case';
const FIELD_LABEL = 'E2E Import Name';

let groupName = '';
let fieldId = 0;
let fieldName = '';
let dateFieldId = 0;
let dateFieldName = '';
let caseId = 0;
let caseTypeId = 0;
let contactId = 0;

/** APIv4 in the authenticated browser session (used to read records back). */
async function api4(page: Page, entity: string, action: string, params: Record<string, unknown>): Promise<any> {
  await page.waitForFunction(() => typeof (window as unknown as { CRM?: { api4?: unknown } }).CRM?.api4 === 'function');
  return page.evaluate(
    ([e, a, p]) => (window as unknown as { CRM: { api4: (e: string, a: string, p: unknown) => Promise<any> } }).CRM.api4(e, a, p),
    [entity, action, params] as const,
  );
}

/** APIv3 in the browser (the importer is an api3 entity, as csvimport calls it). */
async function api3(page: Page, entity: string, action: string, params: Record<string, unknown>): Promise<any> {
  await page.waitForFunction(() => typeof (window as unknown as { CRM?: { api3?: unknown } }).CRM?.api3 === 'function');
  return page.evaluate(
    async ([e, a, p]) => {
      const CRM = (window as unknown as { CRM: { api3: (e: string, a: string, p: unknown) => Promise<any> } }).CRM;
      try {
        return await CRM.api3(e, a, p);
      }
      catch (err: any) {
        return { is_error: 1, error_message: (err && err.error_message) || String(err) };
      }
    },
    [entity, action, params] as const,
  );
}

test.beforeAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  await civi.deleteCustomGroupByTitle(GROUP_TITLE);

  const group = civi.values(await civi.api3('CustomGroup', 'create', {
    title: GROUP_TITLE, extends: 'Case', is_multiple: 1, style: 'Tab with table',
  }))[0];
  groupName = String(group.name);
  const field = civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: group.id, label: FIELD_LABEL, data_type: 'String', html_type: 'Text', is_active: 1, is_searchable: 1,
  }))[0];
  fieldId = Number(field.id);
  fieldName = String(field.name);
  const dateField = civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: group.id, label: 'E2E Import Date', data_type: 'Date', html_type: 'Select Date', is_active: 1, is_searchable: 1,
  }))[0];
  dateFieldId = Number(dateField.id);
  dateFieldName = String(dateField.name);

  caseTypeId = Number(civi.values(await civi.api3('CaseType', 'get', { is_active: 1, options: { limit: 1, sort: 'id ASC' } }))[0].id);
  contactId = Number(civi.values(await civi.api3('Contact', 'create', {
    contact_type: 'Individual', first_name: 'E2E', last_name: 'Import Client',
  }))[0].id);
  caseId = Number(civi.values(await civi.api3('Case', 'create', {
    case_type_id: caseTypeId, contact_id: contactId, creator_id: contactId,
    subject: 'E2E Import Repeatable', status_id: 'Open',
  }))[0].id);
});

test.afterAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  if (caseId) await civi.api3('Case', 'delete', { id: caseId }).catch(() => {});
  await civi.deleteCustomGroupByTitle(GROUP_TITLE).catch(() => {});
  if (contactId) await civi.api3('Contact', 'delete', { id: contactId, skip_undelete: 1 }).catch(() => {});
});

test('CaseCustomImporter.create imports rows (create), updates by id, and rejects bad matches', async ({ page }) => {
  await civiLogin(page);
  await page.goto('/civicrm/admin/search', { waitUntil: 'domcontentloaded' });
  const col = `custom_${fieldId}`;

  // AC1: the group's field, case_id and the id match-key are importable columns.
  const fields = await api3(page, 'CaseCustomImporter', 'getfields', { action: 'create' });
  expect(Object.keys(fields.values || {})).toContain(col);
  expect(Object.keys(fields.values || {})).toContain('case_id');
  expect(Object.keys(fields.values || {})).toContain('id');

  // AC2/AC3: two rows (no id) -> two separate records against the same Case.
  const r1 = await api3(page, 'CaseCustomImporter', 'create', { case_id: caseId, [col]: 'Imported A' });
  expect(r1.is_error).toBeFalsy();
  const r2 = await api3(page, 'CaseCustomImporter', 'create', { case_id: caseId, [col]: 'Imported B' });
  expect(r2.is_error).toBeFalsy();

  let rows = await api4(page, `Custom_${groupName}`, 'get', {
    where: [['entity_id', '=', caseId]], select: ['id', fieldName], orderBy: { id: 'ASC' },
  });
  expect(rows.length).toBe(2);

  // Update: id matches an existing record -> updates it in place across
  // MULTIPLE fields, no new row. id is a match key only (not written as a value).
  const dateCol = `custom_${dateFieldId}`;
  const targetId = rows[0].id;
  const upd = await api3(page, 'CaseCustomImporter', 'create', {
    case_id: caseId, id: targetId, [col]: 'Updated A', [dateCol]: '2026-09-15',
  });
  expect(upd.is_error).toBeFalsy();
  rows = await api4(page, `Custom_${groupName}`, 'get', {
    where: [['entity_id', '=', caseId]], select: ['id', fieldName, dateFieldName],
  });
  expect(rows.length).toBe(2);
  const updatedRow = rows.find((r: any) => r.id === targetId);
  expect(updatedRow[fieldName]).toBe('Updated A');
  expect(String(updatedRow[dateFieldName])).toContain('2026-09-15');

  // Matching failure: an id with no record on this Case is rejected (no new row).
  const badId = await api3(page, 'CaseCustomImporter', 'create', { case_id: caseId, id: 999999, [col]: 'Nope' });
  expect(badId.is_error).toBeTruthy();
  expect(String(badId.error_message)).toContain('No repeatable record');

  // A non-numeric id is rejected (api3 type validation catches it before the
  // service; the service's own "Invalid record id" guard is covered by PHPUnit).
  const badIdFmt = await api3(page, 'CaseCustomImporter', 'create', { case_id: caseId, id: 'abc', [col]: 'Nope' });
  expect(badIdFmt.is_error).toBeTruthy();

  // No stray rows created by any of the rejected imports.
  rows = await api4(page, `Custom_${groupName}`, 'get', { where: [['entity_id', '=', caseId]], select: ['id'] });
  expect(rows.length).toBe(2);

  // AC5: an unknown Case is rejected.
  const bad = await api3(page, 'CaseCustomImporter', 'create', { case_id: 999999, [col]: 'x' });
  expect(bad.is_error).toBeTruthy();
  expect(String(bad.error_message)).toContain('not found');
});
