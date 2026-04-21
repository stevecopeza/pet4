# PET Tech-Debt & Deferred Work Log

## Status
ACTIVE BACKLOG — updated 2026-04-20

## Context
This document captures issues identified during an external read-only code review
(2026-04-20) that were either:
- out of scope for the current sprint, or
- lower-risk than the three security fixes addressed immediately.

The three issues fixed in the same session are noted in the header for completeness but
are **not** tracked here.

### Fixed immediately (same session — not tracked below)
| # | Issue | Fix |
|---|-------|-----|
| F1 | `checkReadPermission()` returned `is_user_logged_in()` across 15+ REST controllers | Replaced with `PortalPermissionHelper::check('pet_sales','pet_hr','pet_manager')` |
| F2 | SQL backup written to web-accessible uploads directory | Added `.htaccess` deny-all + random token in filename (`SystemController.php`) |
| F3 | Raw exception messages leaked to REST clients | Created `RestError::message()` helper; 35 controllers updated |
| D1 | `PersistingEventBus` stored PHP FQCN instead of dotted event name | Added `name(): string` to `SourcedEvent` interface; implemented in 19 concrete classes; bus updated to call `$event->name()` |
| D5 | No static analysis in quality gate | Installed `phpstan/phpstan` + `php-stubs/wordpress-stubs`; configured at level 5; fixed 6 real bugs found; baselined 88 pre-existing issues; `composer analyse` now exits 0 |

---

## Deferred items

### D1 — PersistingEventBus: use dotted event names ✅ DONE 2026-04-20
**Priority:** Medium  
**Area:** `src/Infrastructure/Event/PersistingEventBus.php`

The domain-events schema docs specify event names like `quote.accepted`, `ticket.created`.
The current implementation stores the PHP FQCN (e.g. `App\Domain\Quote\Event\QuoteAccepted`)
as the event type.

**Impact:** Any downstream system or query that relies on the documented string format
(audit queries, webhook routing) gets the wrong value.

**Fix:** Map the event class to its registered string name before persisting, or add a
`name(): string` method to the base `DomainEvent` interface.

---

### D2 — Migration docs: describe actual runtime behaviour
**Priority:** Low  
**Area:** `docs/32_schema_management/01_schema_management_overview.md` (and related)

The docs describe migrations as running at plugin activation / WP upgrade. The actual
implementation runs them at `plugins_loaded` on every request (guarded by a version
comparison). The docs are misleading to anyone reading the codebase for the first time.

**Fix:** Update the migration docs to describe the `plugins_loaded` hook, version-check
guard, and idempotency guarantee accurately.

---

### D3 — Remove stale implementation-status claims from docs
**Priority:** Low  
**Area:** Any `PET_Implementation_Status_*.md` files in `docs/`

As of 2026-04-20 the plugin has 112 E2E portal tests; earlier status docs claim zero
portal test coverage. Stale status docs create confusion about what is and isn't done.

**Fix:** Either delete old status snapshots or clearly mark them superseded and point
readers to `MEMORY.md` / the test suite for current state.

---

### D4 — Remove "PROPOSED" status from foundation governance docs
**Priority:** Low  
**Area:**
- `docs/00_foundations/Documentation_Authority_Order.md`
- `docs/00_foundations/Documentation_Status_and_Supersession_Rules.md`

Both files carry a `## Status: PROPOSED AUTHORITATIVE FOUNDATION DOCUMENT` header.
They are actively used and enforced; the "PROPOSED" label is confusing.

**Fix:** Change status line to `AUTHORITATIVE FOUNDATION DOCUMENT` (drop "PROPOSED").

---

### D5 — Add phpstan / psalm to the quality gate ✅ DONE 2026-04-20
**Priority:** Medium  
**Area:** `composer.json`, `phpstan.neon`, `phpstan-baseline.neon`, `phpstan-bootstrap.php`

The current quality gate is `npm test` (Jest + Playwright). There is no static analysis
pass on the PHP layer. The permission-gate bug (F1) and exception-leakage bug (F3) were
caught by manual review, not tooling.

**Fix:** Add `phpstan` (or `psalm`) at level 5 as a `composer` dev-dependency and wire it
into a `make lint-php` / pre-push hook. PHPStan level 5 catches undefined variables,
wrong return types, and many `mixed`-type paths without being noisy.

---

### D6 — OpenAPI / Swagger artifact
**Priority:** Low (only needed if external consumers emerge)  
**Area:** `docs/26_api_contract/`

The API contract docs claim an OpenAPI artifact exists or will be generated.
No such artifact currently exists.

**Fix:** If a third-party consumer is ever onboarded, generate the spec from controller
annotations or write it by hand. Until then, keep the prose API contract in
`docs/26_api_contract/` and do not imply a machine-readable spec is available.

---

### D7 — QuoteController.checkLoggedIn() — consider hardening
**Priority:** Low  
**Area:** `src/UI/Rest/Controller/QuoteController.php`

`QuoteController` uses its own `checkLoggedIn()` method (`is_user_logged_in()`) instead
of the capability gate. This is intentional: the domain layer enforces role-specific
rules. However it means any authenticated WP user can attempt quote operations, with
rejection happening a layer later.

**Fix (optional):** Replace `checkLoggedIn()` with
`PortalPermissionHelper::check('pet_sales', 'pet_manager')` to fail-fast at the HTTP
layer and reduce surface area. The domain-layer enforcement can be retained as a
defence-in-depth backstop.

---

### D8 — Advisory / Dashboard controllers: upgrade from `edit_posts` to `manage_options`
**Priority:** Low  
**Area:** Advisory and Dashboard REST controllers (check for `current_user_can('edit_posts')`)

Some admin-only controllers gate access on `edit_posts` (editor-level capability) rather
than `manage_options` (admin-only). Advisory and executive-dashboard data should only be
accessible to administrators.

**Fix:** Grep for `edit_posts` in REST controllers; upgrade each to `manage_options`
unless the endpoint genuinely needs to serve editors.

---

## Template for new items

```
### D{N} — Short title
**Priority:** High / Medium / Low
**Area:** file path or component name

Description of the issue.

**Fix:** What to do.
```
