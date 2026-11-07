# PR Reviewer Checklist for Gemini Code Assist

When reviewing Pull Requests, apply every check that fits the repo. Post comments on specific lines with a severity label. Never auto-approve.

**Scope first.** Sections 1 to 7, 9 and 10 apply everywhere. Three sections are
conditional:

- **Section 8 (CiviCRM-Specific)**: CiviCRM extensions, Drupal modules and client sites.
- **Section 11 (Drupal 7 Profile Update Hooks)**: only repos with an `updates/*.php` directory, which in practice is the compuclient profile.
- **Section 12 (Infrastructure)**: infrastructure repos, meaning Ansible, Terraform, Jenkins, Docker images and compose profiles, and scripts that run unattended on production hosts.

Section 12 is mutually exclusive with 8 and 11 in practice. Do not raise CiviCRM
or update-hook findings on an infra PR, or infra findings on an extension PR.

---

## Severity Labels

Use these prefixes in every review comment:

| Label | Meaning | Action |
|-------|---------|--------|
| **BLOCKER** | Security vulnerability, data loss risk, broken functionality | Must fix before merge |
| **WARNING** | Affects quality, performance, or maintainability | Should fix before merge |
| **SUGGESTION** | Optional improvement | Nice to have, can be follow-up |
| **QUESTION** | Needs clarification from the author | Author must respond |

---

## 1. Correctness

- Logic matches the ticket/spec acceptance criteria
- Error handling covers realistic failure modes
- Return types and null checks are correct

## 2. Security (BLOCKER if violated)

- No hardcoded secrets, API keys, or credentials in code
- Parameterized queries used (no SQL injection)
- User input sanitized before rendering (no XSS)
- CSRF protections in place on forms and state-changing endpoints
- All user input validated/escaped before use
- Authentication/authorization enforced on API endpoints
- Webhook signatures verified (where applicable)
- No sensitive files committed (`civicrm.settings.php`, `.env`)
- No hardcoded configurable values (financial types, custom field names, entity IDs)

## 3. Performance (WARNING)

- No N+1 query patterns
- No inefficient loops over large datasets
- No expensive SQL anti-patterns:
  - Expensive `COUNT(*)` on large tables (use `COUNT(id)` or conditional alternatives)
  - Leading-wildcard `LIKE '%...'`
  - Unnecessary subqueries, `GROUP BY`, or `SELECT DISTINCT`
  - Unindexed JOINs
- Columns in `WHERE`/`ORDER BY`/`JOIN` are indexed; CiviCRM custom fields have **Is Searchable** enabled
- Unnecessary API calls avoided (use cached records)
- Hooks are scoped to specific entities/forms (not firing on every page)
- Caching used where appropriate (`Civi::cache()` or static caching)
- Heavy operations queued via `CRM_Core_Job` (not run inline during HTTP requests)

## 4. Code Quality (WARNING)

- Functions follow single responsibility principle
- Naming is clear, self-documenting, and follows project conventions
- No dead code, unused imports, or commented-out blocks
- Dependencies are injected, not hardcoded
- Proper types in PHPDoc annotations (no `mixed` where avoidable)
- `@phpstan-param` / `@phpstan-var` used where linter and PHPStan conflict
- Return type declarations on service methods
- No `assert()` in production code
- Modern PHP features used where appropriate (typed properties, match expressions, enums)
- Proper exception handling (custom exception classes where appropriate)
- Functions/methods are small and focused (< ~50 lines where practical)

## 5. Resilience & Data Handling (WARNING)

- Race conditions handled where multiple requests may modify the same data (database locks, `CRM_Core_Lock`)
- Retry logic with backoff for external API calls
- Rate limits respected on external services
- Pagination, sorting, and filters persist across views
- No-results states and fallback UIs implemented
- No PII logged or stored beyond what is necessary
- CiviCRM privacy fields and contact preferences respected
- Data retention considered -- no indefinite storage of transient data
- Logging uses structured context at appropriate levels -- no sensitive data in logs
- No critical or high-severity warnings in error logs

## 6. Accessibility (WARNING for UI changes)

- ARIA labels on interactive elements
- Keyboard navigation works (no mouse-only interactions)
- Colour contrast meets WCAG AA minimum
- Screen reader compatibility for dynamic content

## 7. Testing (WARNING)

- New features and bug fixes include unit tests
- Tests cover positive, negative, and edge cases
- Validation failures tested -- invalid input handled gracefully
- Permission testing included (admin + minimal-permission users)
- Tests follow Arrange-Act-Assert pattern
- External APIs are mocked, not called directly
- No tests removed or weakened to make them pass
- Error message changes reflected in test assertions
- No `sleep()` calls in tests
- No hardcoded IDs or dates that will break later
- Tests run in isolation (no dependency on other tests)

## 8. CiviCRM-Specific (WARNING unless noted, extensions/modules/sites only)

- APIv4 used -- not raw SQL or APIv3 without justification
- User-facing strings wrapped in `ts()` for i18n
- Extension metadata files updated if needed (`.info.xml`, `composer.json`)
- **BLOCKER**: No new entries added to PHPStan baseline -- fix the code instead
- **BLOCKER**: Auto-generated files (DAO, `.civix.php`) not manually edited
- **BLOCKER**: Sensitive files not committed (`civicrm.settings.php`, `.env`)
- `is_array()` guard on APIv4 `->first()` results

## 9. Static Analysis & Linting (WARNING)

- Code passes PHPStan level 9
- Coding standards followed (CiviCRM/Drupal)
- Files end with newlines
- `@phpstan-param` / `@phpstan-var` used where linter and PHPStan conflict

## 10. Process (WARNING)

- Commit messages follow the `TICKET-###:` convention, using the project's own key
- Commit subject under 72 characters, no trailing period
- No AI attribution in commits
- PR template sections are **conditional**: only the applicable ones are filled, and
  inapplicable ones are **omitted entirely** rather than marked "None" or "N/A".
  A trivial PR with `## Overview` alone is complete. **Do not flag a PR for missing
  sections that do not apply to it.**
- `## Before` is expected only where the change alters *existing* user-visible
  behaviour; a brand-new feature can use `## After` alone

## 11. Drupal 7 Profile Update Hooks (compuclient only)

Applies only in repos with an `updates/*.php` update hook directory (the compuclient profile). Skip this section everywhere else.

- **BLOCKER**: A new `updates/NNNN.php` file must be numbered exactly for the *next planned release of the branch the PR targets* — `7` + major + zero-padded 2-digit minor (release `7.x-7.2` → `7702`; release `7.x-4.13` → `7413`). The next release is the highest *bare* release tag of that line (`git tag -l | grep -E '^7\.x-<major>\.[0-9]+$' | sort -V | tail -1`, ignoring `-alpha`/`-beta`/`-rc`/`-patch`/`-test`/`automation/*` tags) with its minor incremented by 1. Flag any hook numbered beyond that.
- **BLOCKER**: If the next release's hook file already exists, new update code must be added to it — not to a new, higher-numbered file.
- Each line (`7.x-7.x`, `7.x-4.x`) has its own hook sequence; a 4.x PR must never introduce a `77xx` hook or vice versa.
- Function name must match the filename (`updates/7702.php` → `compuclient_update_7702()`).

## 12. Infrastructure (infra repos only)

Full detail in
[.ai/infra.md](https://github.com/compucorp/dev-ai-playbooks/blob/master/.ai/infra.md).
These changes run unattended on production hosts, so failures are silent and
repeat nightly until someone notices.

### Resource bounds (BLOCKER if unbounded on production-sized input)

- Memory and disk are bounded by a constant the author chose, not by input size, row count or compression ratio
- The bound is stated in a comment **and** backed by a measurement, not asserted (a docstring claiming "O(1) memory" with no number is the finding, not the defence)
- Peak usage times worker concurrency fits the smallest host it runs on
- Library resource defaults that form part of the budget are pinned, or the residual floor is stated (for example `boto3` `TransferConfig` buffering)
- Timeouts on every subprocess, HTTP call and SSH invocation

### Failure behaviour (BLOCKER)

- Non-zero exit on every failure path; `set -euo pipefail` in shell, and `PIPESTATUS` checked in pipelines
- The produced artefact is verified (size floor, trailer, record count), not inferred from an exit code
- Writes are atomic: staged, verified, then promoted. The last known-good artefact is never overwritten before its replacement is checked
- Idempotent and safe to re-run after a partial failure; retries bounded, with backoff, and only for transient errors

### Detection (BLOCKER for new or changed scheduled work)

- Failure reaches a human through an alert, monitor or notified job
- Scheduled work has a heartbeat: a job that stops being scheduled emits no failure, so only alerting on the *absence* of a success signal detects it
- Validation of a stored artefact runs outside the job that writes it. An in-pipeline verifier can only report on runs that happened
- A change to backup or restore code states when the restore path was last exercised end to end. Verifying a dump was written is not evidence it can be loaded

### Environment parity (WARNING)

- CI runtime version pinned to what production runs; a floating `python-version: '3.x'` or equivalent is a finding when the code depends on interpreter or library specifics
- Base image assumptions checked: UID/GID, package names, available binaries

### Testing (WARNING, BLOCKER if the suite never runs)

- Every test file is executed by a CI job. A new or existing test file that no workflow job runs is a BLOCKER
- A new regression test is verified to fail against the unfixed code, with the observed value quoted in the PR
- Assertions distinguish the fix from a plausible broken variant, rather than passing for both
- Fakes call the way the real client calls (chunk sizes, ordering, error types); fixtures are production-shaped
- A dry run is included where the tool has one: `ansible-playbook --check --diff`, `terraform plan`, `docker compose config`, `nginx -t`
- Mechanical checks belong in CI rather than in review comments. Where a linter can decide it, flag the missing CI job instead of the individual violation: `ansible-lint --profile safety`, `terraform fmt`/`validate`/`tflint`/`checkov`, `hadolint`, `shellcheck`, `flake8`

### Secrets and blast radius (BLOCKER)

- No secrets in the repo, in `argv` (visible in `ps`), or in job output
- New IAM policies, roles and DB grants scoped to the minimum that works
- Destructive operations require an explicit target and refuse a wildcard default

### Agent-written infrastructure code (WARNING, BLOCKER where it changes the plan)

These are mistakes an agent makes that read as correct on the page:

- Invented provider arguments, module versions, role names or host names. They are plausible and non-existent; they are ruled out by running `terraform validate` / `ansible-lint`, not by reading the diff
- A resource rename with no `moved` block: a cosmetic-looking diff that destroys and recreates
- A new module or role written where an existing `compucorp.*` one would do
- Code that assumes the repo matches production when drift exists
- No `terraform apply`, `destroy`, `state rm`/`mv`, `docker stack deploy` or production `ansible-playbook` from an agent session. The agent produces diffs, the pipeline deploys

### Tool-specific (WARNING)

- **Terraform**: plan attached; any destroy or replace of a stateful resource (RDS, EBS, S3, EFS) is a BLOCKER until explained; provider credentials and state backend point at the same account; `required_version`, provider versions and module versions pinned; sensitive variables marked `sensitive = true` and no state files committed; RDS changes state whether they defer to the maintenance window
- **Ansible**: modules over `shell`; `changed_when`/`failed_when` set; no `state: latest` or floating role versions; inventory matches live infrastructure
- **Docker/Swarm**: image tags or digests pinned; healthcheck, resource limits and restart policy set; persistent data on shared storage; a change needing a restart says how it will be applied
- **Jenkins**: pipeline Groovy parses (a stray backslash or `\uXXXX`, including inside a comment, aborts at parse time); no reliance on stage-set variables surviving Restart-from-Stage

### PR description (WARNING)

- States scope of effect (which hosts, clients, accounts), rollout path, rollback, and what was verified versus what still needs a real run
- States rollout order. Default is staged: internal or dev swarm, then one client, then the rest. A change that reaches every client at once should say why it cannot be staged

---

## Review Style

- Be specific -- reference file paths and line numbers
- Explain **why** something is an issue, not just what to change
- Think critically -- do not suggest changes that contradict architectural decisions
- Consider implications of type changes, database constraints, and performance trade-offs
- Distinguish severity clearly -- not everything is a blocker
- Be constructive -- suggest fixes, not just problems
