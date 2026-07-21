/**
 * TCOSB-59 — 1.5 SearchKit: Support Repeatable Case Custom Field Sets.
 *
 * This capability is NATIVE: a repeatable ("Tab with table") Case custom group
 * is exposed by APIv4 as a `Custom_<name>` entity whose `entity_id` field has
 * `fk_entity = Case`, so SearchKit offers it as a join from Case exactly like a
 * Contact multi-record group. No civicase runtime code is required — this spec
 * is a regression guard so that if a future core/civicase change ever breaks the
 * case join, dedup, or filtering, it is caught.
 *
 * Verified through `SearchDisplay.run` (the exact call the SearchKit UI makes),
 * driven in the authenticated admin session via CRM.api4:
 *  - AC1/AC2: Case→Custom_<group> join is usable and filterable.
 *  - AC3:     a filter on a repeatable custom field returns the matching Case
 *             (and a non-matching filter returns nothing).
 *  - AC5:     all of a Case's repeatable records are considered by the filter.
 *  - AC6:     GROUP BY Case yields ONE row even when several child records match
 *             (no duplicate Cases).
 *
 * Self-contained: creates its own repeatable Case group + field + two Cases and
 * their records, and tears them down.
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../helpers/fixtures';
import { civiLogin } from '../helpers/civicrm-login';
import { CiviCrmApi } from '../helpers/civicrm-api';

const GROUP_TITLE = 'ZZ E2E Search Repeat Case';
const FIELD_LABEL = 'E2E Search Name';
const CASE_TYPE_ID = 2;
const CLIENT_CONTACT_ID = 3;

let groupName = '';
let fieldName = '';
let caseMatchId = 0;
let caseOtherId = 0;

/** Call APIv4 inside the authenticated browser session (as the SearchKit UI does). */
async function api4(page: Page, entity: string, action: string, params: Record<string, unknown>): Promise<any[]> {
  return page.evaluate(
    ([e, a, p]) => (window as unknown as { CRM: { api4: (e: string, a: string, p: unknown) => Promise<any[]> } })
      .CRM.api4(e, a, p),
    [entity, action, params] as const,
  );
}

/** Build the inline SavedSearch that joins Case to the repeatable group and filters it. */
function caseSearch(where: unknown[]): Record<string, unknown> {
  return {
    savedSearch: {
      api_entity: 'Case',
      api_params: {
        version: 4,
        select: ['id', 'subject', 'COUNT(m.id) AS matches'],
        join: [[`Custom_${groupName} AS m`, 'INNER', ['id', '=', 'm.entity_id']]],
        where,
        groupBy: ['id'],
      },
    },
    display: null,
    return: 'page:1',
  };
}

test.beforeAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  await civi.deleteCustomGroupByTitle(GROUP_TITLE);

  const group = civi.values(
    await civi.api3('CustomGroup', 'create', {
      title: GROUP_TITLE,
      extends: 'Case',
      is_multiple: 1,
      style: 'Tab with table',
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

  caseMatchId = Number(civi.values(await civi.api3('Case', 'create', {
    case_type_id: CASE_TYPE_ID, contact_id: CLIENT_CONTACT_ID, creator_id: CLIENT_CONTACT_ID,
    subject: 'E2E Search MATCH', status_id: 'Open',
  }))[0].id);

  caseOtherId = Number(civi.values(await civi.api3('Case', 'create', {
    case_type_id: CASE_TYPE_ID, contact_id: CLIENT_CONTACT_ID, creator_id: CLIENT_CONTACT_ID,
    subject: 'E2E Search OTHER', status_id: 'Open',
  }))[0].id);
});

test.afterAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  for (const id of [caseMatchId, caseOtherId]) {
    if (id) await civi.api3('Case', 'delete', { id }).catch(() => {});
  }
  await civi.deleteCustomGroupByTitle(GROUP_TITLE).catch(() => {});
});

test('SearchKit joins, filters and de-duplicates repeatable Case custom data', async ({ page }) => {
  await civiLogin(page);
  // Any CiviCRM page exposes CRM.api4; the SearchKit admin is a natural home.
  await page.goto('/civicrm/admin/search', { waitUntil: 'domcontentloaded' });

  // The matching Case has TWO repeatable records (so dedup is actually exercised);
  // the other Case has one non-matching record.
  await api4(page, `Custom_${groupName}`, 'save', {
    records: [
      { entity_id: caseMatchId, [fieldName]: 'MatchAlpha' },
      { entity_id: caseMatchId, [fieldName]: 'MatchBeta' },
      { entity_id: caseOtherId, [fieldName]: 'SomethingElse' },
    ],
  });

  // AC3 / AC5 / AC6: filter matches the two records on caseMatch; GROUP BY Case
  // returns exactly ONE row for it (matches = 2), and caseOther is excluded.
  const matched = await api4(page, 'SearchDisplay', 'run', caseSearch([['m.' + fieldName, 'LIKE', 'Match%']]));
  expect(matched).toHaveLength(1);
  expect(matched[0].data.id).toBe(caseMatchId);
  expect(matched[0].data.matches).toBe(2);

  // AC3 (negative): a filter that matches no record returns no Cases.
  const none = await api4(page, 'SearchDisplay', 'run', caseSearch([['m.' + fieldName, '=', 'NoSuchValueXYZ']]));
  expect(none).toHaveLength(0);
});
