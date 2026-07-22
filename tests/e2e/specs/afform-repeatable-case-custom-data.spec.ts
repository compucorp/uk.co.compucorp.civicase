/**
 * TCOSB-60 — 1.6 Form Builder (Afform): Support Repeatable Case Custom Field Sets.
 *
 * A repeatable ("Tab with table") Case custom group is exposed by core as an
 * Afform BLOCK (`afblockCustom_<name>`, entity_type=Case, join_entity=Custom_<name>)
 * — byte-for-byte the same shape core produces for Contact multi-record groups.
 * So Afform's native `af-repeat` handles it with no civicase runtime code: a form
 * with the block set to repeat submits multiple records, each stored as a
 * separate Custom_<name> record linked to the parent Case via entity_id.
 *
 * This spec is a regression guard driving `Afform.submit` (the exact call the
 * public form makes) in the authenticated session:
 *  - AC2/AC3/AC9: submitting two repeated instances creates two separate records
 *    against the Case.
 *  - AC4: the group's max_multiple is enforced — a submission beyond the limit
 *    does not create additional records.
 *
 * Self-contained: creates its own repeatable Case group (max 2) + field + Case
 * and the Afform, and tears them all down (Afform via revert — it has no delete).
 */

import { type Page } from '@playwright/test';
import { test, expect } from '../helpers/fixtures';
import { civiLogin } from '../helpers/civicrm-login';
import { CiviCrmApi } from '../helpers/civicrm-api';

const GROUP_TITLE = 'ZZ E2E Afform Repeat Case';
const FIELD_LABEL = 'E2E Afform Name';
const MAX_RECORDS = 2;
const AFFORM_NAME = 'afformE2eRepeatCaseTest';
const CASE_TYPE_ID = 2;
const CLIENT_CONTACT_ID = 3;

let groupName = '';
let fieldName = '';
let caseId = 0;

/** Call APIv4 inside the authenticated browser session (as the public form does). */
async function api4(page: Page, entity: string, action: string, params: Record<string, unknown>): Promise<any> {
  return page.evaluate(
    ([e, a, p]) => (window as unknown as { CRM: { api4: (e: string, a: string, p: unknown) => Promise<any> } })
      .CRM.api4(e, a, p),
    [entity, action, params] as const,
  );
}

/** Count this group's records currently attached to the case. */
async function recordCount(page: Page): Promise<number> {
  const rows = await api4(page, `Custom_${groupName}`, 'get', {
    where: [['entity_id', '=', caseId]], select: ['id'],
  });
  return Array.isArray(rows) ? rows.length : 0;
}

test.beforeAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  await civi.deleteCustomGroupByTitle(GROUP_TITLE);

  const group = civi.values(await civi.api3('CustomGroup', 'create', {
    title: GROUP_TITLE, extends: 'Case', is_multiple: 1,
    max_multiple: MAX_RECORDS, style: 'Tab with table',
  }))[0];
  groupName = String(group.name);

  const field = civi.values(await civi.api3('CustomField', 'create', {
    custom_group_id: group.id, label: FIELD_LABEL,
    data_type: 'String', html_type: 'Text', is_active: 1, is_searchable: 1,
  }))[0];
  fieldName = String(field.name);

  caseId = Number(civi.values(await civi.api3('Case', 'create', {
    case_type_id: CASE_TYPE_ID, contact_id: CLIENT_CONTACT_ID, creator_id: CLIENT_CONTACT_ID,
    subject: 'E2E Afform Repeatable', status_id: 'Open',
  }))[0].id);
});

test.afterAll(async () => {
  const civi = CiviCrmApi.fromEnv();
  if (caseId) await civi.api3('Case', 'delete', { id: caseId }).catch(() => {});
  await civi.deleteCustomGroupByTitle(GROUP_TITLE).catch(() => {});
});

test('Afform af-repeat creates multiple Case custom records and enforces the max', async ({ page }) => {
  await civiLogin(page);
  await page.goto('/civicrm/admin/search', { waitUntil: 'domcontentloaded' });

  const layout =
    `<af-form ctrl="afform">` +
    `<af-entity type="Custom_${groupName}" name="Record" label="Rec" actions="{create: true}" security="RBAC" />` +
    `<fieldset af-fieldset="Record" af-repeat="Add" min="1" max="${MAX_RECORDS}">` +
    `<af-field name="entity_id" defn="{input_type: 'Hidden', label: false}" />` +
    `<af-field name="${fieldName}" />` +
    `</fieldset></af-form>`;

  try {
    // A form that repeats the Case custom group's fieldset (Form Builder output).
    await api4(page, 'Afform', 'revert', { where: [['name', '=', AFFORM_NAME]] }).catch(() => {});
    await api4(page, 'Afform', 'create', {
      values: {
        name: AFFORM_NAME, type: 'form', title: 'E2E Repeat Case',
        server_route: 'civicrm/af/e2e-repeat-case-test',
        is_public: false, permission: ['access CiviCRM'], layout,
      },
    });

    // AC2/AC3/AC9: submit two repeated instances -> two separate records on the Case.
    await api4(page, 'Afform', 'submit', {
      name: AFFORM_NAME,
      values: { Record: [
        { fields: { entity_id: caseId, [fieldName]: 'Entry A' } },
        { fields: { entity_id: caseId, [fieldName]: 'Entry B' } },
      ] },
    });
    expect(await recordCount(page)).toBe(2);

    // AC4: at the max now — an extra submission must not create a third record.
    await api4(page, 'Afform', 'submit', {
      name: AFFORM_NAME,
      values: { Record: [{ fields: { entity_id: caseId, [fieldName]: 'Entry C (over limit)' } }] },
    }).catch(() => { /* rejection is an acceptable enforcement path */ });
    expect(await recordCount(page)).toBe(MAX_RECORDS);
  } finally {
    // Afform has no delete; revert removes the local-only form.
    await api4(page, 'Afform', 'revert', { where: [['name', '=', AFFORM_NAME]] }).catch(() => {});
  }
});
