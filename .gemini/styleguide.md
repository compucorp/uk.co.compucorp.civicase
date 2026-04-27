# PR Reviewer Checklist for Gemini Code Assist

When reviewing Pull Requests, apply every check below. Post comments on specific lines with a severity label. Never auto-approve.

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

## 8. CiviCRM-Specific (WARNING unless noted)

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

- Commit messages follow `COMCL-###:` convention
- No AI attribution in commits
- PR template completed with all required sections (Overview, Before, After, Technical Details)

---

## Review Style

- Be specific -- reference file paths and line numbers
- Explain **why** something is an issue, not just what to change
- Think critically -- do not suggest changes that contradict architectural decisions
- Consider implications of type changes, database constraints, and performance trade-offs
- Distinguish severity clearly -- not everything is a blocker
- Be constructive -- suggest fixes, not just problems
