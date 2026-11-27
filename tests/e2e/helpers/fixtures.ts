/**
 * Playwright fixtures for CiviCase E2E tests.
 */

import { test as base } from '@playwright/test';
import { CiviCrmApi } from './civicrm-api';

type TestFixtures = {
  civi: CiviCrmApi;
};

export const test = base.extend<TestFixtures>({
  civi: async ({}, use) => {
    await use(CiviCrmApi.fromEnv());
  },
});

export { expect } from '@playwright/test';
