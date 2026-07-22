/**
 * TCOSB-62 — 1.7 CiviCRM Exports: Support Repeatable Case Custom Field Sets.
 *
 * Core's legacy Export UI cannot export multi-record custom data (true even for
 * Contacts — see the spec's open question), and for Cases it has no custom-data
 * export path at all. The sanctioned route is SearchKit's "Download Spreadsheet"
 * (SearchDisplay.download), which is self-contained (League\Csv over the
 * display's rows — it does NOT use the legacy export field-select; that is the
 * separate "Export Cases" task). Because a repeatable Case group is a native
 * SearchKit join (see 1.5), export works with no civicase runtime code.
 *
 * Regression guard driving the export row-set in the authenticated session.
 * SearchDisplay.download streams a file (not returnable via api4), so we assert
 * on SearchDisplay.run — the exact rows the download serialises.
 *  - AC2: every repeatable record on the Case is a separate export row.
 *  - AC3: each row carries the parent Case's identifying columns.
 *  - AC4: values of multiple field types (text, date, number) are exported.
 *  - AC5: a non-repeatable Case custom field still exports (no regression).
 *
 * Self-contained: creates its own groups + fields + Case + records (no hardcoded
 * IDs) and tears them all down.
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../helpers/fixtures';
import { civiLogin } from '../helpers/civicrm-login';
import { CiviCrmApi } from '../helpers/civicrm-api';

const GROUP_TITLE = 'ZZ E2E Export Repeat Case';
const STD_GROUP_TITLE = 'ZZ E2E Export Std Case';

let groupName = '';
let fName = '';   // text
let fDate = '';   // date
let fNum = '';    // integer
let stdGroupName = '';
let stdFieldName = '';
let caseId = 0;
let caseTypeId = 0;
let contactId = 0;

async function api4(page: Page, entity: string, action: string, params: Record<string, unknown>): Promise<any> {
  await page.waitForFunction(() => typeof (window as unknown as { CRM?: { api4?: unknown } }).CRM?.api4 === 'function');
  return page.evaluate(
    ([e, a, p]) => (window as unknown as { CRM: { api4: (e: string, a: string, p: unknown) => Promise<any> } }).CRM.api4(e, a, p),
    [entity, action, params] as const,
  );
}

test.beforeAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  await civi.deleteCustomGroupByTitle(GROUP_TITLE);
  await civi.deleteCustomGroupByTitle(STD_GROUP_TITLE);

  // Repeatable group with three field types (text / date / integer).
  const group = civi.values(await civi.api3('CustomGroup', 'create', {
    title: GROUP_TITLE, extends: 'Case', is_multiple: 1, style: 'Tab with table',
  }))[0];
  groupName = String(group.name);
  fName = String(civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: group.id, label: 'Entry Name', data_type: 'String', html_type: 'Text', is_active: 1, is_searchable: 1,
  }))[0].name);
  fDate = String(civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: group.id, label: 'Entry Date', data_type: 'Date', html_type: 'Select Date', is_active: 1, is_searchable: 1,
  }))[0].name);
  fNum = String(civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: group.id, label: 'Entry Num', data_type: 'Int', html_type: 'Text', is_active: 1, is_searchable: 1,
  }))[0].name);

  // A non-repeatable Case custom group (AC5 regression check).
  const stdGroup = civi.values(await civi.api3('CustomGroup', 'create', {
    title: STD_GROUP_TITLE, extends: 'Case', is_multiple: 0, style: 'Inline',
  }))[0];
  stdGroupName = String(stdGroup.name);
  stdFieldName = String(civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: stdGroup.id, label: 'Std Field', data_type: 'String', html_type: 'Text', is_active: 1, is_searchable: 1,
  }))[0].name);

  caseTypeId = Number(civi.values(await civi.api3('CaseType', 'get', { is_active: 1, options: { limit: 1, sort: 'id ASC' } }))[0].id);
  contactId = Number(civi.values(await civi.api3('Contact', 'create', {
    contact_type: 'Individual', first_name: 'E2E', last_name: 'Export Client',
  }))[0].id);
  caseId = Number(civi.values(await civi.api3('Case', 'create', {
    case_type_id: caseTypeId, contact_id: contactId, creator_id: contactId,
    subject: 'E2E Export Repeatable', status_id: 'Open',
  }))[0].id);
});

test.afterAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  if (caseId) await civi.api3('Case', 'delete', { id: caseId }).catch(() => {});
  await civi.deleteCustomGroupByTitle(GROUP_TITLE).catch(() => {});
  await civi.deleteCustomGroupByTitle(STD_GROUP_TITLE).catch(() => {});
  if (contactId) await civi.api3('Contact', 'delete', { id: contactId, skip_undelete: 1 }).catch(() => {});
});

test('SearchKit export emits one row per repeatable Case record (all field types), non-repeatable unaffected', async ({ page }) => {
  await civiLogin(page);
  await page.goto('/civicrm/admin/search', { waitUntil: 'domcontentloaded' });

  // Two repeatable records with text / date / number values.
  await api4(page, `Custom_${groupName}`, 'save', {
    records: [
      { entity_id: caseId, [fName]: 'Export A', [fDate]: '2026-08-01', [fNum]: 11 },
      { entity_id: caseId, [fName]: 'Export B', [fDate]: '2026-08-02', [fNum]: 22 },
    ],
  });
  // A value for the non-repeatable group's field on the same Case.
  await api4(page, 'Case', 'update', {
    values: { [`${stdGroupName}.${stdFieldName}`]: 'StdValue' },
    where: [['id', '=', caseId]],
  });

  // Export shape: Case joined to the repeatable group, NO group-by => one row
  // per record (this is what "Download Spreadsheet" serialises).
  const rows = await api4(page, 'SearchDisplay', 'run', {
    savedSearch: {
      api_entity: 'Case',
      api_params: {
        version: 4,
        select: ['id', 'subject', `m.${fName}`, `m.${fDate}`, `m.${fNum}`],
        join: [[`Custom_${groupName} AS m`, 'INNER', ['id', '=', 'm.entity_id']]],
        where: [['id', '=', caseId]],
        orderBy: { [`m.${fNum}`]: 'ASC' },
      },
    },
    display: null,
    return: 'page:1',
  });
  const data = (Array.isArray(rows) ? rows : []).map((r: any) => r.data || r);

  // AC2: two records -> two rows. AC3: each row carries the parent Case id.
  expect(data).toHaveLength(2);
  for (const d of data) expect(Number(d.id)).toBe(caseId);
  // AC4: text / date / number values all exported.
  expect(data.map((d: any) => d[`m.${fName}`])).toEqual(['Export A', 'Export B']);
  expect(data.map((d: any) => Number(d[`m.${fNum}`]))).toEqual([11, 22]);
  for (const d of data) expect(String(d[`m.${fDate}`])).toContain('2026-08-0');

  // AC5: a non-repeatable Case custom field still exports normally.
  const std = await api4(page, 'SearchDisplay', 'run', {
    savedSearch: {
      api_entity: 'Case',
      api_params: { version: 4, select: ['id', `${stdGroupName}.${stdFieldName}`], where: [['id', '=', caseId]] },
    },
    display: null,
    return: 'page:1',
  });
  const stdData = (Array.isArray(std) ? std : []).map((r: any) => r.data || r);
  expect(stdData).toHaveLength(1);
  expect(stdData[0][`${stdGroupName}.${stdFieldName}`]).toBe('StdValue');
});
