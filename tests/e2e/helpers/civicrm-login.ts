/**
 * CiviCRM / Drupal admin login helper.
 *
 * Adapted from the uk.co.compucorp.stripe E2E harness. Uses the standard
 * Drupal login form and copes with both the single-step and multi-step
 * (SSP) login layouts.
 */

import type { Page } from '@playwright/test';

export async function civiLogin(page: Page) {
  const user = process.env.CIVICRM_ADMIN_USER || 'compuco_admin';
  const pass = process.env.CIVICRM_ADMIN_PASS || 'compuco_admin';

  await page.goto('/user/login');
  await page.waitForTimeout(2_000);

  // Already logged in?
  if (page.url().match(/\/user\/\d/)) {
    return;
  }

  const passwordField = page.locator('#edit-pass');
  const isFullForm = await passwordField.isVisible({ timeout: 2_000 }).catch(() => false);

  if (isFullForm) {
    const usernameField = page.locator('#user-login input[name="name"]:visible');
    if (await usernameField.isVisible().catch(() => false)) {
      await usernameField.fill(user);
    }
    await passwordField.fill(pass);
    await page.locator('#user-login button[type="submit"]:visible, #user-login input[type="submit"]:visible').first().click();
  } else {
    const usernameInput = page.locator('input[name="name"]:visible');
    await usernameInput.fill(user);
    await page.locator('button[type="submit"]:visible, input[type="submit"]:visible').first().click();
    await page.locator('#edit-pass').waitFor({ state: 'visible', timeout: 10_000 });
    await page.locator('#edit-pass').fill(pass);
    await page.locator('#user-login button[type="submit"]:visible, #user-login input[type="submit"]:visible').first().click();
  }

  await page.waitForURL(/(?!.*\/user\/login).*/, { timeout: 15_000 }).catch(() => {});
  await page.waitForTimeout(2_000);
}
