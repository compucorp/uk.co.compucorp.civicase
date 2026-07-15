# CiviCase E2E tests (Playwright)

On-demand browser tests that drive a running CiviCRM site. Mirrors the harness
in `uk.co.compucorp.stripe`. Not wired into CI — run locally (or against a
cc-test site) when you need UI-level verification.

## Setup

```bash
cd tests/e2e
cp .env.e2e.example .env.e2e   # then edit values
npm install
npx playwright install chromium
```

`.env.e2e` values:

| var | meaning |
|-----|---------|
| `CIVICRM_BASE_URL` | Site under test, e.g. `http://compuclient-87f26a.localhost:8080` |
| `CIVICRM_ADMIN_USER` / `CIVICRM_ADMIN_PASS` | Drupal admin login used for the UI |
| `CIVICRM_API_KEY` | `api_key` of an admin contact (setup/teardown via REST) |
| `CIVICRM_SITE_KEY` | Site key (`CIVICRM_SITE_KEY` in `civicrm.settings.php`) |
| `BASIC_AUTH_USER` / `BASIC_AUTH_PASS` | Only if the site is behind HTTP basic auth |

## Run

```bash
npm test                 # headless
npm run test:ui          # Playwright UI mode
npm run report           # open last HTML report
```

## Specs

- `repeatable-case-custom-fields.spec.ts` — TCOSB-23: the Custom Group admin
  form exposes "Allow multiple records", "Maximum records" and "Tab with table"
  when the group is used for a Case, and the configuration saves (AC1 / AC2).
