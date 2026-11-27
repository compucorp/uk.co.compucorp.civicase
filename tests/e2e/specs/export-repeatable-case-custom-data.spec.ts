/**
 * TCOSB-62 — 1.7 SearchKit Exports: Support Repeatable Case Custom Field Sets.
 *
 * Export goes through SearchKit's "Download Spreadsheet" action
 * (SearchDisplay.download). A repeatable Case group is a native SearchKit join
 * from Case (see 1.5), so this works with no civicase runtime code. This spec
 * is the regression guard for the 1.7 acceptance criteria.
 *
 * It builds a Case search the way an admin does: the repeatable groups joined
 * with the join aliases the SearchKit UI uses, saved with a Table display whose
 * columns are SearchKit's own defaults. It then downloads the display as CSV
 * with the same request the "Download Spreadsheet" button sends, and checks the
 * file. Other variations use SearchDisplay.download with format "array", which
 * returns the header and rows that a spreadsheet would contain.
 *
 *  - AC1:  the repeatable groups are offered as Case joins, with their fields.
 *  - AC2:  the Table display downloads as a CSV file.
 *  - AC3:  each repeatable record is its own row.
 *  - AC4:  each row carries the parent Case's ID and Subject.
 *  - AC5:  text, date, number and money values are exported.
 *  - AC6:  display filters, sorting and search conditions on a repeatable
 *          field shape the export.
 *  - AC7:  a non-repeatable Case custom field still exports.
 *  - AC9:  a repeatable Contact group exports one row per record too.
 *  - AC10: with an optional join, a Case without records is one blank row.
 *  - AC11: two joined groups give one row per combination (2 x 3 = 6).
 *  - AC12: each group's record ID is exportable, headed
 *          "<Group title>: Custom Value ID" in the UI and in the file.
 *
 * AC8 (permissions) needs a user without full access, which this admin-only
 * harness cannot log in as. It is covered by the PHPUnit test
 * CRM_Civicase_RepeatableCaseCustomDataExportTest.
 *
 * Self-contained: creates its own groups, fields, contact, Cases, records and
 * saved search, and removes them all.
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../helpers/fixtures';
import { civiLogin } from '../helpers/civicrm-login';
import { CiviCrmApi } from '../helpers/civicrm-api';

const GROUP_TITLE = 'ZZ E2E Export Repeat Case';
const GROUP_B_TITLE = 'ZZ E2E Export Repeat Case B';
const STD_GROUP_TITLE = 'ZZ E2E Export Std Case';
const CONTACT_GROUP_TITLE = 'ZZ E2E Export Repeat Contact';
const SEARCH_NAME = 'ZZ_E2E_Export_Repeat_Case';
const DISPLAY_NAME = 'ZZ_E2E_Export_Repeat_Case_Table';

let groupName = '';
let fName = '';   // text
let fDate = '';   // date
let fNum = '';    // integer
let fAmount = ''; // money
let groupBName = '';
let fItem = '';
let stdGroupName = '';
let stdFieldName = '';
let contactGroupName = '';
let fContactEntry = '';
let caseId = 0;
let emptyCaseId = 0;
let caseTypeId = 0;
let contactId = 0;

// Join aliases as the SearchKit UI names them (`<join alias>_01`).
const aliasA = () => `Case_Custom_${groupName}_entity_id_01`;
const aliasB = () => `Case_Custom_${groupBName}_entity_id_01`;

type Row = string[];

async function api4(page: Page, entity: string, action: string, params: Record<string, unknown>): Promise<any> {
  await page.waitForFunction(() => typeof (window as unknown as { CRM?: { api4?: unknown } }).CRM?.api4 === 'function');
  return page.evaluate(
    ([e, a, p]) => (window as unknown as { CRM: { api4: (e: string, a: string, p: unknown) => Promise<any> } }).CRM.api4(e, a, p),
    [entity, action, params] as const,
  );
}

/**
 * SearchDisplay.download with format "array": the header row, then one row
 * per result, as the spreadsheet would contain them.
 */
async function downloadRows(page: Page, params: Record<string, unknown>): Promise<{ header: Row; rows: Row[] }> {
  const result = await api4(page, 'SearchDisplay', 'download', { ...params, format: 'array' });
  const [header, ...rows] = (Array.isArray(result) ? result : []) as unknown[][];
  const asText = (row: unknown[]) => row.map((value) => (value === null || value === undefined ? '' : String(value)));
  return { header: asText(header ?? []), rows: rows.map(asText) };
}

/** Downloads a saved display as CSV, with the request the Download Spreadsheet button sends. */
async function downloadCsv(page: Page, params: Record<string, unknown>) {
  return page.evaluate(async (apiParams) => {
    const crm = (window as unknown as { CRM: { url: (path: string) => string } }).CRM;
    const response = await fetch(crm.url('civicrm/ajax/api4/SearchDisplay/download'), {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ params: JSON.stringify(apiParams) }).toString(),
    });
    return {
      status: response.status,
      disposition: response.headers.get('Content-Disposition') ?? '',
      text: await response.text(),
    };
  }, { ...params, format: 'csv' });
}

/** Parses CSV text (quoted fields, doubled quotes, optional BOM) into rows. */
function parseCsv(text: string): Row[] {
  const rows: Row[] = [];
  let row: Row = [];
  let field = '';
  let quoted = false;
  const input = text.replace(/^﻿/, '');
  for (let i = 0; i < input.length; i++) {
    const char = input[i];
    if (quoted) {
      if (char === '"' && input[i + 1] === '"') {
        field += '"';
        i++;
      }
      else if (char === '"') {
        quoted = false;
      }
      else {
        field += char;
      }
    }
    else if (char === '"') {
      quoted = true;
    }
    else if (char === ',') {
      row.push(field);
      field = '';
    }
    else if (char === '\n' || char === '\r') {
      if (char === '\r' && input[i + 1] === '\n') i++;
      row.push(field);
      rows.push(row);
      row = [];
      field = '';
    }
    else {
      field += char;
    }
  }
  if (field !== '' || row.length) {
    row.push(field);
    rows.push(row);
  }
  return rows;
}

/** Values of one column, found by its header label. */
function column(header: Row, rows: Row[], label: string): string[] {
  const index = header.indexOf(label);
  expect(index, `column "${label}" in ${JSON.stringify(header)}`).toBeGreaterThanOrEqual(0);
  return rows.map((row) => row[index] ?? '');
}

async function createField(civi: CiviCrmApi, groupId: unknown, label: string, dataType: string, htmlType: string): Promise<string> {
  return String(civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: groupId, label, data_type: dataType, html_type: htmlType, is_active: 1, is_searchable: 1,
  }))[0].name);
}

async function deleteSavedSearch(civi: CiviCrmApi): Promise<void> {
  // Deleting the saved search also deletes its displays.
  const searches = civi.values(await civi.api3('SavedSearch', 'get', { sequential: 1, name: SEARCH_NAME, return: 'id' }));
  for (const search of searches) {
    await civi.api3('SavedSearch', 'delete', { id: search.id });
  }
}

test.beforeAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  await deleteSavedSearch(civi);
  for (const title of [GROUP_TITLE, GROUP_B_TITLE, STD_GROUP_TITLE, CONTACT_GROUP_TITLE]) {
    await civi.deleteCustomGroupByTitle(title);
  }

  // Repeatable group A: text / date / integer / money.
  const group = civi.values(await civi.api3('CustomGroup', 'create', {
    title: GROUP_TITLE, extends: 'Case', is_multiple: 1, style: 'Tab with table',
  }))[0];
  groupName = String(group.name);
  fName = await createField(civi, group.id, 'Entry Name', 'String', 'Text');
  fDate = await createField(civi, group.id, 'Entry Date', 'Date', 'Select Date');
  fNum = await createField(civi, group.id, 'Entry Num', 'Int', 'Text');
  fAmount = await createField(civi, group.id, 'Entry Amount', 'Money', 'Text');

  // Repeatable group B, for joining two groups at once (AC11).
  const groupB = civi.values(await civi.api3('CustomGroup', 'create', {
    title: GROUP_B_TITLE, extends: 'Case', is_multiple: 1, style: 'Tab with table',
  }))[0];
  groupBName = String(groupB.name);
  fItem = await createField(civi, groupB.id, 'Item', 'String', 'Text');

  // A non-repeatable Case group (AC7).
  const stdGroup = civi.values(await civi.api3('CustomGroup', 'create', {
    title: STD_GROUP_TITLE, extends: 'Case', is_multiple: 0, style: 'Inline',
  }))[0];
  stdGroupName = String(stdGroup.name);
  stdFieldName = await createField(civi, stdGroup.id, 'Std Field', 'String', 'Text');

  // A repeatable Contact group (AC9).
  const contactGroup = civi.values(await civi.api3('CustomGroup', 'create', {
    title: CONTACT_GROUP_TITLE, extends: 'Individual', is_multiple: 1, style: 'Tab with table',
  }))[0];
  contactGroupName = String(contactGroup.name);
  fContactEntry = await createField(civi, contactGroup.id, 'Contact Entry', 'String', 'Text');

  caseTypeId = Number(civi.values(await civi.api3('CaseType', 'get', { is_active: 1, options: { limit: 1, sort: 'id ASC' } }))[0].id);
  contactId = Number(civi.values(await civi.api3('Contact', 'create', {
    contact_type: 'Individual', first_name: 'E2E', last_name: 'Export Client',
  }))[0].id);
  caseId = Number(civi.values(await civi.api3('Case', 'create', {
    case_type_id: caseTypeId, contact_id: contactId, creator_id: contactId,
    subject: 'E2E Export Repeatable', status_id: 'Open',
  }))[0].id);
  emptyCaseId = Number(civi.values(await civi.api3('Case', 'create', {
    case_type_id: caseTypeId, contact_id: contactId, creator_id: contactId,
    subject: 'E2E Export No Records', status_id: 'Open',
  }))[0].id);
});

test.afterAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  await deleteSavedSearch(civi).catch(() => {});
  for (const id of [caseId, emptyCaseId]) {
    if (id) await civi.api3('Case', 'delete', { id }).catch(() => {});
  }
  for (const title of [GROUP_TITLE, GROUP_B_TITLE, STD_GROUP_TITLE, CONTACT_GROUP_TITLE]) {
    await civi.deleteCustomGroupByTitle(title).catch(() => {});
  }
  if (contactId) await civi.api3('Contact', 'delete', { id: contactId, skip_undelete: 1 }).catch(() => {});
});

test('SearchKit Download Spreadsheet exports repeatable Case custom data (1.7)', async ({ page }) => {
  await civiLogin(page);
  await page.goto('/civicrm/admin/search', { waitUntil: 'domcontentloaded' });

  // Case with 2 records in group A, 3 in group B and a non-repeatable value;
  // a second Case with none; a contact with 2 records in the Contact group.
  await api4(page, `Custom_${groupName}`, 'save', {
    records: [
      { entity_id: caseId, [fName]: 'Export A', [fDate]: '2026-08-01', [fNum]: 11, [fAmount]: 1000.5 },
      { entity_id: caseId, [fName]: 'Export B', [fDate]: '2026-08-02', [fNum]: 22, [fAmount]: 20 },
    ],
  });
  await api4(page, `Custom_${groupBName}`, 'save', {
    records: ['Item 1', 'Item 2', 'Item 3'].map((item) => ({ entity_id: caseId, [fItem]: item })),
  });
  await api4(page, 'Case', 'update', {
    values: { [`${stdGroupName}.${stdFieldName}`]: 'StdValue' },
    where: [['id', '=', caseId]],
  });
  await api4(page, `Custom_${contactGroupName}`, 'save', {
    records: [
      { entity_id: contactId, [fContactEntry]: 'Contact entry 1' },
      { entity_id: contactId, [fContactEntry]: 'Contact entry 2' },
    ],
  });

  const a = aliasA();
  const b = aliasB();
  const idHeaderA = `${GROUP_TITLE}: Custom Value ID`;
  const idHeaderB = `${GROUP_B_TITLE}: Custom Value ID`;
  const searchParams = {
    version: 4,
    select: [
      'id', 'subject', `${stdGroupName}.${stdFieldName}`,
      `${a}.id`, `${a}.${fName}`, `${a}.${fDate}`, `${a}.${fNum}`, `${a}.${fAmount}`,
      `${b}.id`, `${b}.${fItem}`,
    ],
    join: [
      [`Custom_${groupName} AS ${a}`, 'LEFT', ['id', '=', `${a}.entity_id`]],
      [`Custom_${groupBName} AS ${b}`, 'LEFT', ['id', '=', `${b}.entity_id`]],
    ],
    where: [['id', 'IN', [caseId, emptyCaseId]]],
  };
  const saved = await api4(page, 'SavedSearch', 'create', {
    values: { name: SEARCH_NAME, label: 'ZZ E2E Export Repeat Case', api_entity: 'Case', api_params: searchParams },
  });
  const searchId = Number(saved[0]?.id);
  expect(searchId).toBeGreaterThan(0);

  await test.step('AC1/AC12: the search editor offers the groups and labels their record ID', async () => {
    await page.goto(`/civicrm/admin/search#/edit/${searchId}`, { waitUntil: 'domcontentloaded' });
    await page.locator('crm-search-admin').waitFor({ state: 'attached', timeout: 30_000 });
    const ui = await page.evaluate(([entityA, entityB, keys]: [string, string, string[]]) => {
      const w = window as unknown as {
        CRM: { crmSearchAdmin: { joins: Record<string, { entity: string; label: string }[]>; schema: { name: string; fields: { name: string }[] }[] } };
        angular: { element: (el: Element | null) => { injector: () => { get: (name: string) => { getDefaultLabel: (expr: string) => string } } } };
      };
      const admin = w.CRM.crmSearchAdmin;
      const searchMeta = w.angular.element(document.querySelector('crm-search-admin')).injector().get('searchMeta');
      return {
        joins: (admin.joins.Case ?? []).filter((join) => [entityA, entityB].includes(join.entity))
          .map((join) => ({ entity: join.entity, label: join.label })),
        fieldsA: (admin.schema.find((entity) => entity.name === entityA)?.fields ?? []).map((field) => field.name),
        labels: keys.map((key) => searchMeta.getDefaultLabel(key)),
      };
    }, [`Custom_${groupName}`, `Custom_${groupBName}`, [`${a}.id`, `${b}.id`]] as [string, string, string[]]);

    expect(ui.joins).toEqual(expect.arrayContaining([
      { entity: `Custom_${groupName}`, label: GROUP_TITLE },
      { entity: `Custom_${groupBName}`, label: GROUP_B_TITLE },
    ]));
    expect(ui.fieldsA).toEqual(expect.arrayContaining(['id', fName, fDate, fNum, fAmount]));
    expect(ui.labels).toEqual([idHeaderA, idHeaderB]);
  });

  // A Table display with SearchKit's default columns, and actions turned on so
  // that Download Spreadsheet is available.
  const defaults = await api4(page, 'SearchDisplay', 'getDefault', { savedSearch: SEARCH_NAME });
  const settings = defaults[0]?.settings ?? {};
  const columns = (settings.columns ?? []) as { key?: string; label?: string }[];
  // The header SearchKit gives each column, by field key.
  const labelFor = (key: string): string => {
    const label = columns.find((col) => col.key === key)?.label;
    expect(label, `default column for ${key}`).toBeTruthy();
    return String(label);
  };
  expect(labelFor(`${a}.id`)).toBe(idHeaderA);
  expect(labelFor(`${b}.id`)).toBe(idHeaderB);
  await api4(page, 'SearchDisplay', 'create', {
    values: {
      saved_search_id: searchId,
      name: DISPLAY_NAME,
      label: 'ZZ E2E Export Table',
      type: 'table',
      settings: { ...settings, actions: true, sort: [['id', 'ASC'], [`${a}.id`, 'ASC'], [`${b}.id`, 'ASC']] },
    },
  });

  await test.step('AC2-AC5, AC7, AC10-AC12: the downloaded CSV', async () => {
    const file = await downloadCsv(page, { savedSearch: SEARCH_NAME, display: DISPLAY_NAME, filters: {} });
    expect(file.status).toBe(200);
    expect(file.disposition).toContain('.csv');
    const [header, ...rows] = parseCsv(file.text).filter((row) => row.some((value) => value !== ''));

    // AC12: each group's record ID column, headed "<Group title>: Custom Value ID".
    expect(header).toEqual(expect.arrayContaining([idHeaderA, idHeaderB]));

    const caseIds = column(header, rows, labelFor('id')).map(Number);
    // Values of a column on the Case's rows, and on the record-less Case's rows.
    const caseValues = (key: string) => column(header, rows, labelFor(key)).filter((_, i) => caseIds[i] === caseId);
    const emptyValues = (key: string) => column(header, rows, labelFor(key)).filter((_, i) => caseIds[i] === emptyCaseId);
    const recordsA = caseValues(`${a}.id`);
    const recordsB = caseValues(`${b}.id`);

    // AC11: 2 records in A x 3 in B = 6 rows for the Case, each combination once.
    expect(recordsA).toHaveLength(6);
    expect(new Set(recordsA.map((id, i) => `${id}/${recordsB[i]}`)).size).toBe(6);

    // AC3: every record is exported, none is lost or merged.
    expect(new Set(recordsA).size).toBe(2);
    expect(new Set(recordsB).size).toBe(3);
    for (const id of [...recordsA, ...recordsB]) expect(Number(id)).toBeGreaterThan(0);

    // AC4: the Case's Subject on every one of its rows.
    expect(new Set(caseValues('subject'))).toEqual(new Set(['E2E Export Repeatable']));

    // AC5: values of each type, per record. Dates are exported raw.
    const names = caseValues(`${a}.${fName}`);
    const dates = caseValues(`${a}.${fDate}`);
    const nums = caseValues(`${a}.${fNum}`);
    const amounts = caseValues(`${a}.${fAmount}`);
    for (const [i, name] of names.entries()) {
      const isA = name === 'Export A';
      expect(['Export A', 'Export B']).toContain(name);
      expect(dates[i]).toBe(isA ? '2026-08-01' : '2026-08-02');
      expect(nums[i]).toBe(isA ? '11' : '22');
      expect(amounts[i]).toMatch(isA ? /1,?000\.50/ : /20\.00/);
    }
    expect(new Set(caseValues(`${b}.${fItem}`))).toEqual(new Set(['Item 1', 'Item 2', 'Item 3']));

    // AC7: the non-repeatable field on each of the Case's rows.
    expect(new Set(caseValues(`${stdGroupName}.${stdFieldName}`))).toEqual(new Set(['StdValue']));

    // AC10: the Case without records is one row, its group columns blank.
    expect(emptyValues(`${a}.id`)).toEqual(['']);
    expect(emptyValues(`${a}.${fName}`)).toEqual(['']);
    expect(emptyValues(`${b}.id`)).toEqual(['']);
  });

  await test.step('AC6: display filters and sorting shape the export', async () => {
    const filtered = await downloadRows(page, {
      savedSearch: SEARCH_NAME,
      display: DISPLAY_NAME,
      filters: { [`${a}.${fName}`]: 'Export B' },
    });
    expect(column(filtered.header, filtered.rows, labelFor(`${a}.${fName}`)))
      .toEqual(['Export B', 'Export B', 'Export B']);

    const sorted = await downloadRows(page, {
      savedSearch: SEARCH_NAME,
      display: DISPLAY_NAME,
      filters: { id: caseId },
      sort: [[`${a}.${fNum}`, 'DESC']],
    });
    expect(column(sorted.header, sorted.rows, labelFor(`${a}.${fNum}`)))
      .toEqual(['22', '22', '22', '11', '11', '11']);
  });

  await test.step('AC6: a search condition on a repeatable field keeps matching records only', async () => {
    const { header, rows } = await downloadRows(page, {
      savedSearch: {
        api_entity: 'Case',
        api_params: {
          version: 4,
          select: ['id', `${a}.${fName}`],
          join: [[`Custom_${groupName} AS ${a}`, 'INNER', ['id', '=', `${a}.entity_id`]]],
          where: [['id', 'IN', [caseId, emptyCaseId]], [`${a}.${fNum}`, '>=', 20]],
        },
      },
      display: {
        type: 'table',
        label: 'Filtered',
        settings: {
          actions: true,
          columns: [
            { type: 'field', key: 'id', label: 'Case ID' },
            { type: 'field', key: `${a}.${fName}`, label: 'Entry Name' },
          ],
        },
      },
    });
    expect(header).toEqual(['Case ID', 'Entry Name']);
    expect(rows).toEqual([[String(caseId), 'Export B']]);
  });

  await test.step('AC9: a repeatable Contact group exports one row per record', async () => {
    const r = `Contact_Custom_${contactGroupName}_entity_id_01`;
    const { header, rows } = await downloadRows(page, {
      savedSearch: {
        api_entity: 'Contact',
        api_params: {
          version: 4,
          select: ['id', 'display_name', `${r}.id`, `${r}.${fContactEntry}`],
          join: [[`Custom_${contactGroupName} AS ${r}`, 'INNER', ['id', '=', `${r}.entity_id`]]],
          where: [['id', '=', contactId]],
        },
      },
      display: {
        type: 'table',
        label: 'Contact export',
        settings: {
          actions: true,
          columns: [
            { type: 'field', key: 'id', label: 'Contact ID' },
            { type: 'field', key: 'display_name', label: 'Display Name' },
            { type: 'field', key: `${r}.id`, label: `${CONTACT_GROUP_TITLE}: Custom Value ID` },
            { type: 'field', key: `${r}.${fContactEntry}`, label: 'Contact Entry' },
          ],
          sort: [[`${r}.id`, 'ASC']],
        },
      },
    });
    expect(rows).toHaveLength(2);
    expect(column(header, rows, 'Contact ID')).toEqual([String(contactId), String(contactId)]);
    expect(new Set(column(header, rows, 'Display Name')).size).toBe(1);
    expect(column(header, rows, 'Contact Entry')).toEqual(['Contact entry 1', 'Contact entry 2']);
  });

  await test.step('AC7: a search with only non-repeatable fields is unchanged', async () => {
    const { header, rows } = await downloadRows(page, {
      savedSearch: {
        api_entity: 'Case',
        api_params: { version: 4, select: ['id', `${stdGroupName}.${stdFieldName}`], where: [['id', '=', caseId]] },
      },
      display: {
        type: 'table',
        label: 'Std export',
        settings: {
          actions: true,
          columns: [
            { type: 'field', key: 'id', label: 'Case ID' },
            { type: 'field', key: `${stdGroupName}.${stdFieldName}`, label: 'Std Field' },
          ],
        },
      },
    });
    expect(header).toEqual(['Case ID', 'Std Field']);
    expect(rows).toEqual([[String(caseId), 'StdValue']]);
  });
});
