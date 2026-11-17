/**
 * TCOSB-64 — 1.8 CiviCRM Imports: Support Repeatable Case Custom Field Sets.
 *
 * Core's Import framework has no additive path for repeatable Case custom data,
 * so 1.8 is delivered additively via nz.co.fuzion.csvimport ("CSV GUI Import to
 * api", installed) calling a thin civicase API endpoint, CaseCustomImporter.create,
 * once per CSV row (same pattern as uk.co.compucorp.membershipextrasimporterapi).
 * The parent Case is matched by Case ID. Each repeatable group has its own
 * "<Group title>: Custom Value ID" column: blank creates a record in that
 * group, populated updates that record in place.
 *
 * This spec drives CaseCustomImporter.create directly (the exact call csvimport
 * makes per row) in the authenticated session — a real regression guard without
 * the brittle csvimport QuickForm UI (that round-trip is covered by manual QA),
 * and without the DDL-leak that blocks creating a real custom group in a headless
 * PHPUnit test.
 *  - AC1/AC2: the group's fields and its Custom Value ID are importable columns.
 *  - AC5/AC6: two rows create two separate records against the same Case.
 *  - AC7/AC17: the group's ID updates a record in place; a blank cell clears.
 *  - AC8/AC9: a bad ID or an unknown Case is rejected with an error.
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
let groupId = 0;
let fieldId = 0;
let fieldName = '';
let dateFieldId = 0;
let dateFieldName = '';
let levelFieldId = 0;
let countryFieldId = 0;
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
  groupId = Number(group.id);
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
  const levelField = civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: group.id, label: 'E2E Import Level', data_type: 'String', html_type: 'Select', is_active: 1,
    option_label: ['Bachelor', 'Master'], option_value: ['bachelor', 'master'], option_status: [1, 1],
  }))[0];
  levelFieldId = Number(levelField.id);
  const countryField = civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: group.id, label: 'E2E Import Country', data_type: 'Country', html_type: 'Select', is_active: 1,
  }))[0];
  countryFieldId = Number(countryField.id);

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

test('CaseCustomImporter.create imports rows (create), updates by the group ID, and rejects bad matches', async ({ page }) => {
  await civiLogin(page);
  await page.goto('/civicrm/admin/search', { waitUntil: 'domcontentloaded' });
  const col = `custom_${fieldId}`;
  const idCol = `custom_value_id_${groupId}`;

  // AC1/AC2: the group's field, case_id and the group's Custom Value ID
  // (labelled as a SearchKit export labels it) are importable columns.
  const fields = await api3(page, 'CaseCustomImporter', 'getfields', { action: 'create' });
  expect(Object.keys(fields.values || {})).toContain(col);
  expect(Object.keys(fields.values || {})).toContain('case_id');
  expect(fields.values?.[idCol]?.title).toBe(`${GROUP_TITLE}: Custom Value ID`);
  expect(Object.keys(fields.values || {})).not.toContain('id');

  // AC5/AC6: two rows (no ID) -> two separate records against the same Case.
  const r1 = await api3(page, 'CaseCustomImporter', 'create', { case_id: caseId, [col]: 'Imported A' });
  expect(r1.is_error).toBeFalsy();
  const r2 = await api3(page, 'CaseCustomImporter', 'create', { case_id: caseId, [col]: 'Imported B' });
  expect(r2.is_error).toBeFalsy();

  let rows = await api4(page, `Custom_${groupName}`, 'get', {
    where: [['entity_id', '=', caseId]], select: ['id', fieldName], orderBy: { id: 'ASC' },
  });
  expect(rows.length).toBe(2);

  // AC7: the group's ID matches an existing record -> updates it in place
  // across MULTIPLE fields, no new row. The ID is a match key only.
  const dateCol = `custom_${dateFieldId}`;
  const targetId = rows[0].id;
  const upd = await api3(page, 'CaseCustomImporter', 'create', {
    case_id: caseId, [idCol]: targetId, [col]: 'Updated A', [dateCol]: '2026-09-15',
  });
  expect(upd.is_error).toBeFalsy();
  rows = await api4(page, `Custom_${groupName}`, 'get', {
    where: [['entity_id', '=', caseId]], select: ['id', fieldName, dateFieldName],
  });
  expect(rows.length).toBe(2);
  const updatedRow = rows.find((r: any) => r.id === targetId);
  expect(updatedRow[fieldName]).toBe('Updated A');
  expect(String(updatedRow[dateFieldName])).toContain('2026-09-15');

  // AC17: on update a mapped blank cell clears the value; an unmapped column
  // (the name here) is left unchanged.
  const clear = await api3(page, 'CaseCustomImporter', 'create', { case_id: caseId, [idCol]: targetId, [dateCol]: '' });
  expect(clear.is_error).toBeFalsy();
  rows = await api4(page, `Custom_${groupName}`, 'get', {
    where: [['id', '=', targetId]], select: ['id', fieldName, dateFieldName],
  });
  expect(rows[0][fieldName]).toBe('Updated A');
  expect(rows[0][dateFieldName]).toBeNull();

  // AC8: an ID with no record in the group is rejected (no new row).
  const badId = await api3(page, 'CaseCustomImporter', 'create', { case_id: caseId, [idCol]: 999999, [col]: 'Nope' });
  expect(badId.is_error).toBeTruthy();
  expect(String(badId.error_message)).toBe(`No ${GROUP_TITLE} record has Custom Value ID 999999`);

  // AC8: a non-numeric ID is rejected, naming the group's ID column.
  const badIdFmt = await api3(page, 'CaseCustomImporter', 'create', { case_id: caseId, [idCol]: 'abc', [col]: 'Nope' });
  expect(badIdFmt.is_error).toBeTruthy();
  expect(String(badIdFmt.error_message)).toContain(`${GROUP_TITLE}: Custom Value ID must be a positive whole number`);

  // No stray rows created by any of the rejected imports.
  rows = await api4(page, `Custom_${groupName}`, 'get', { where: [['entity_id', '=', caseId]], select: ['id'] });
  expect(rows.length).toBe(2);

  // AC9: an unknown Case is rejected.
  const bad = await api3(page, 'CaseCustomImporter', 'create', { case_id: 999999, [col]: 'x' });
  expect(bad.is_error).toBeTruthy();
  expect(String(bad.error_message)).toContain('not found');
});

test('CaseCustomImporter identifies option fields so csvimport stores option values, not labels', async ({ page }) => {
  await civiLogin(page);
  await page.goto('/civicrm/admin/search', { waitUntil: 'domcontentloaded' });
  const fields = (await api3(page, 'CaseCustomImporter', 'getfields', { action: 'create' })).values || {};

  // CRM_Import_Parser::getFieldMetadata() loads a multi-record custom field's
  // options — and so converts a CSV label such as "Bachelor" to "bachelor", or
  // a country name to its ID — only for a column carrying these keys;
  // otherwise the label is stored as-is (or, for a Country field, rejected).
  for (const id of [levelFieldId, countryFieldId]) {
    expect(fields).toHaveProperty(`custom_${id}`);
    expect(fields[`custom_${id}`]).toMatchObject({
      custom_field_id: id,
      is_multiple: 1,
      'custom_group_id.name': groupName,
    });
  }
  expect(fields).toHaveProperty(`custom_${fieldId}`);
  expect(fields[`custom_${fieldId}`].custom_field_id).toBeUndefined();
});
