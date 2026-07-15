/**
 * Minimal CiviCRM API3/API4 REST client for E2E setup/teardown.
 *
 * Adapted from the uk.co.compucorp.stripe E2E harness. All operations go over
 * HTTP using api_key + site key auth (no SSH required).
 */

interface CiviApiConfig {
  baseUrl: string;
  apiKey: string;
  siteKey: string;
  basicAuth?: { username: string; password: string };
}

export class CiviCrmApi {
  private config: CiviApiConfig;

  constructor(config: CiviApiConfig) {
    this.config = config;
  }

  static fromEnv(): CiviCrmApi {
    if (!process.env.CIVICRM_BASE_URL || !process.env.CIVICRM_API_KEY || !process.env.CIVICRM_SITE_KEY) {
      throw new Error('Missing required env vars: CIVICRM_BASE_URL, CIVICRM_API_KEY, CIVICRM_SITE_KEY. Copy .env.e2e.example to .env.e2e and configure.');
    }
    return new CiviCrmApi({
      baseUrl: process.env.CIVICRM_BASE_URL,
      apiKey: process.env.CIVICRM_API_KEY,
      siteKey: process.env.CIVICRM_SITE_KEY,
      basicAuth: process.env.BASIC_AUTH_USER
        ? {
            username: process.env.BASIC_AUTH_USER,
            password: process.env.BASIC_AUTH_PASS || '',
          }
        : undefined,
    });
  }

  private authHeaders(): Record<string, string> {
    const headers: Record<string, string> = { 'X-Requested-With': 'XMLHttpRequest' };
    if (this.config.basicAuth) {
      const cred = Buffer.from(
        `${this.config.basicAuth.username}:${this.config.basicAuth.password}`,
      ).toString('base64');
      headers['Authorization'] = `Basic ${cred}`;
    }
    return headers;
  }

  /**
   * Call CiviCRM API3 via REST.
   */
  async api3(
    entity: string,
    action: string,
    params: Record<string, unknown> = {},
  ): Promise<Record<string, unknown>> {
    const url = `${this.config.baseUrl}/civicrm/ajax/rest`;
    const isWrite = ['create', 'update', 'delete', 'setvalue', 'Run', 'run'].includes(action);
    const apiParams = {
      entity,
      action,
      api_key: this.config.apiKey,
      key: this.config.siteKey,
      json: JSON.stringify(params),
    };

    let response: Response;
    if (isWrite) {
      response = await fetch(url, {
        method: 'POST',
        headers: { ...this.authHeaders(), 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(apiParams).toString(),
      });
    } else {
      response = await fetch(`${url}?${new URLSearchParams(apiParams).toString()}`, {
        method: 'GET',
        headers: this.authHeaders(),
      });
    }

    if (!response.ok) {
      throw new Error(`CiviCRM API3 ${entity}.${action} failed: ${response.status} ${response.statusText}`);
    }
    const result = await response.json() as Record<string, unknown>;
    if (result.is_error) {
      throw new Error(`CiviCRM API3 ${entity}.${action} error: ${result.error_message}`);
    }
    return result;
  }

  /**
   * Return the values array from an API3 response as a plain array.
   */
  values(result: Record<string, unknown>): Record<string, unknown>[] {
    const values = result.values;
    if (Array.isArray(values)) return values;
    if (typeof values === 'object' && values !== null) {
      return Object.values(values as Record<string, unknown>) as Record<string, unknown>[];
    }
    return [];
  }

  /**
   * Delete any custom group with the given title (used for teardown).
   */
  async deleteCustomGroupByTitle(title: string): Promise<void> {
    const result = await this.api3('CustomGroup', 'get', { sequential: 1, title, return: 'id' });
    for (const group of this.values(result)) {
      await this.api3('CustomGroup', 'delete', { id: group.id });
    }
  }
}
