/**
 * TCOSB-62 — 1.7 CiviCRM Exports: Support Repeatable Case Custom Field Sets.
 *
 * Core's legacy Export UI cannot export multi-record custom data (true even for
 * Contacts — see the spec's open question), so the sanctioned path is SearchKit
 * export. Because a repeatable Case group is a native SearchKit join (see 1.5),
 * SearchKit's export emits each repeatable record as its own row with the parent
 * Case columns repeated — no civicase runtime code.
 *
 * This spec is a regression guard driving `SearchDisplay.download` (the exact
 * action the SearchKit "Export" button calls) in the authenticated session:
 *  - AC2: every repeatable record on the Case is exported as a separate row.
 *  - AC3: each row carries the parent Case's identifying columns.
 *  - AC4: custom field values are exported.
 *
 * Self-contained: creates its own repeatable Case group + field + Case + records
 * (no hardcoded IDs) and tears them all down.
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../helpers/fixtures';
import { civiLogin } from '../helpers/civicrm-login';
import { CiviCrmApi } from '../helpers/civicrm-api';

const GROUP_TITLE = 'ZZ E2E Export Repeat Case';
const FIELD_LABEL = 'E2E Export Name';

let groupName = '';
let fieldName = '';
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

  const group = civi.values(await civi.api3('CustomGroup', 'create', {
    title: GROUP_TITLE, extends: 'Case', is_multiple: 1, style: 'Tab with table',
  }))[0];
  groupName = String(group.name);

  const field = civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: group.id, label: FIELD_LABEL,
    data_type: 'String', html_type: 'Text', is_active: 1, is_searchable: 1,
  }))[0];
  fieldName = String(field.name);

  caseTypeId = Number(civi.values(await civi.api3('CaseType', 'get', {
    is_active: 1, options: { limit: 1, sort: 'id ASC' },
  }))[0].id);
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
  if (contactId) await civi.api3('Contact', 'delete', { id: contactId, skip_undelete: 1 }).catch(() => {});
});

test('SearchKit export emits one row per repeatable Case record with parent Case columns', async ({ page }) => {
  await civiLogin(page);
  await page.goto('/civicrm/admin/search', { waitUntil: 'domcontentloaded' });

  // Two repeatable records on the Case.
  await api4(page, `Custom_${groupName}`, 'save', {
    records: [
      { entity_id: caseId, [fieldName]: 'Export A' },
      { entity_id: caseId, [fieldName]: 'Export B' },
    ],
  });

  // The SearchKit "Export" button calls SearchDisplay.download, which serialises
  // the display's rows to CSV/XLSX. Those rows are exactly what SearchDisplay.run
  // returns, so we assert on the run result (the download action only streams a
  // file, which isn't returnable via api4). Case joined to the repeatable group
  // with NO group-by => each repeatable record is its own export row.
  const rows = await api4(page, 'SearchDisplay', 'run', {
    savedSearch: {
      api_entity: 'Case',
      api_params: {
        version: 4,
        select: ['id', 'subject', `m.${fieldName}`],
        join: [[`Custom_${groupName} AS m`, 'INNER', ['id', '=', 'm.entity_id']]],
        where: [['id', '=', caseId]],
      },
    },
    display: null,
    return: 'page:1',
  });

  const data = (Array.isArray(rows) ? rows : []).map((r: any) => r.data || r);
  // AC2: two records -> two exported rows.
  expect(data).toHaveLength(2);
  // AC3: every row carries the parent Case id.
  for (const d of data) expect(Number(d.id)).toBe(caseId);
  // AC4: the custom values are present, one per record.
  const values = data.map((d: any) => d[`m.${fieldName}`] ?? d[fieldName]).sort();
  expect(values).toEqual(['Export A', 'Export B']);
});
