import { defineConfig } from '@playwright/test';
import dotenv from 'dotenv';

dotenv.config({ path: '.env.e2e' });

export default defineConfig({
  testDir: './specs',
  timeout: 90_000,
  retries: 0,
  workers: 1, // Sequential — tests share the admin session / site state.
  reporter: [
    ['html', { open: 'never' }],
    ['list'],
  ],
  use: {
    baseURL: process.env.CIVICRM_BASE_URL,
    httpCredentials: process.env.BASIC_AUTH_USER
      ? {
          username: process.env.BASIC_AUTH_USER,
          password: process.env.BASIC_AUTH_PASS || '',
        }
      : undefined,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'civicase-e2e',
      testMatch: /.*\.spec\.ts/,
    },
  ],
});
