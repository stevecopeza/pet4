---
name: pet-project-rules
description: >
  Project rules and conventions for the PET (Plan. Execute. Track) WordPress plugin.
  Use this skill whenever working on any file within the PET plugin codebase — including
  PHP backend code, React/TypeScript frontend code, database migrations, REST API endpoints,
  domain events, DI container wiring, tests, or documentation. These rules must be followed
  for all code changes, reviews, and new feature development in the PET project.
---

# PET Project Rules

## Architecture & Layer Boundaries

- **DDD layer dependency rule:** Domain has zero dependencies on Application, Infrastructure, or UI. Application depends on Domain only. Infrastructure implements Domain interfaces. UI depends on Application and Infrastructure via DI container.
- **Repository pattern:** All data access uses repository interfaces in `Domain/{Context}/Repository/`. Implementations in `Infrastructure/Persistence/Repository/Sql{Name}Repository.php`. Never access `$wpdb` outside Infrastructure.
- **Command/Handler pattern:** Write operations use Command (data) + Handler (logic) in `Application/{Context}/Command/`. Commands are pure data carriers. Handlers receive dependencies via constructor injection.
- **Event-driven side effects:** Cross-context side effects use domain events via `EventBus`, never direct calls. Listeners wired in `pet.php` during `plugins_loaded`. Events implementing `SourcedEvent` are persisted to event stream; plain `DomainEvent` are not.
- **State machine pattern:** Entities with lifecycle states (Quote, Contract, Project, Ticket) use Value Object state classes with `canTransitionTo()` validation. Never assign state from raw strings — use `fromString()` or named constructors.

## PHP Conventions

- Every PHP file: `declare(strict_types=1)`.
- All date/time values: `\DateTimeImmutable`, never `\DateTime`.
- Entity IDs are `?int` (nullable before persistence). Use `id(): ?int` getter.
- Malleable data: entities with user-defined fields carry `malleableData: array` and `malleableSchemaVersion: ?int`, stored as JSON.
- Namespace: `Pet\{Layer}\{Context}\{Type}\{Class}`. Examples: `Pet\Domain\Commercial\Entity\Quote`, `Pet\Application\Support\Command\CreateTicketHandler`, `Pet\Infrastructure\Persistence\Repository\SqlTicketRepository`, `Pet\UI\Rest\Controller\TicketController`.

## Frontend Conventions

- Admin UI is a single React SPA at `src/UI/Admin/`. Routing driven by `window.petSettings.currentPage` (set by WordPress), not client-side router. Each admin page = one top-level component in `src/UI/Admin/components/`.
- API communication via REST at `/wp-json/pet/v1/`. Auth: `X-WP-Nonce` header from `window.petSettings.nonce`. Base URL: `window.petSettings.apiUrl`.
- **Site URL:** Always use `https://pet4.cope.zone/` as the PET project site URL. Never use `localhost` or any local development URL.

## Git & Workflow

- Git repo root is `wp-content/plugins/pet/`, not the WordPress root. `vendor/`, `node_modules/`, `dist/` are gitignored.
- Include `Co-Authored-By: Oz <oz-agent@warp.dev>` at the end of every commit message.
- Use feature branches off `main`. Create PRs for review before merging.

## DI Container

- All bindings in `Infrastructure/DependencyInjection/ContainerFactory::getDefinitions()`. Use `\DI\autowire()` for simple injection. Use explicit closures when `$wpdb` or cross-cutting dependencies are needed.

## Testing

- Unit tests in `tests/Unit/` mirror `src/` structure. Domain tests must be pure PHP — no WordPress or DB dependencies. Integration tests in `tests/Integration/` may use WP test harness.
- E2E tests in `tests/e2e/` use Playwright against a live WordPress instance. Config: `playwright.config.ts`. Run: `npx playwright test`. Env vars in `.env` (see `.env.example`).
- CRUD E2E tests use `test.describe.serial()` and clean up via `afterAll`. Test data uses `E2E Test` prefix.
- Form components must use `htmlFor`/`id` pairs with convention `id="pet-{form}-{field}"` to support `getByLabel()` locators.

## Immutability Principle

- Never edit or delete: accepted quotes, submitted time entries, SLA outcomes, KPI scores, domain events, or baselines.
- Corrections are made via compensating entries (e.g. `CostAdjustment`) or new versions — never by mutating the original record.
- This is the highest-priority design constraint. When in doubt, prefer immutability over convenience.

## Design Priority Chain

When trade-offs arise, resolve them in this order (highest priority first):
1. **Immutability** — operational truth is never rewritten
2. **Governance** — audit trails, variance tracking, approval flows
3. **Integrity** — domain invariants, state machine validity, referential consistency
4. **Survivability** — graceful degradation, error boundaries, idempotency
5. **Compatibility** — backward compatible changes, no breaking migrations
6. **Convenience** — developer ergonomics, UI polish

## Commercial Integrity

- Variance must be explicit — never silently absorb cost/price differences.
- Quotes are versioned. Once accepted, a quote is immutable.
- Adjustments (write-offs, credits) are first-class entities (`CostAdjustment`) with full audit trail (who, when, reason).
- Payment schedule total must match quote total value (enforced by `Quote::validateReadiness()`).

## Integration Rules

- All integrations are event-triggered and idempotent. Use the outbox pattern for external dispatch.
- PET is the authoritative source of truth. External systems are secondary.
- No inbound mutation — external systems cannot write directly to PET domain entities.
- Every integration operation must log explicit success or failure.

## Feature Flag Gating

- Gate features at route registration or handler entry, not deep inside business logic.
- When a feature flag is off, the endpoint returns 404 (not 403). The feature does not exist from the consumer's perspective.
- Admin routes: check feature flag first, then check permission. A disabled feature should never prompt an auth challenge.

## Advisory Boundary

- Advisory outputs (signals, recommendations) are derived read models — never operational truth.
- Advisory data is versioned and annotated with the source signals that produced it.
- Advisory logic must never mutate domain entities. It reads from projections and writes only to `AdvisorySignal`.

## Backward Compatibility

- No destructive or breaking changes to database schema, REST API contracts, or domain event payloads.
- Schema changes are additive (new columns, new tables). Existing columns are never removed or renamed in-place.
- API responses may add new fields but must not remove or rename existing ones.

## Testing Requirements

- All code changes must include corresponding tests.
- Tests must be read-only — no side effects from assertions. Test data setup and teardown must be explicit.
- CI runs all test suites (Unit, Integration, E2E) on every push.

## Migrations

- All migrations registered in `Infrastructure/Persistence/Migration/MigrationRegistry::all()` in strict append-only order. New migrations go at the end. Never reorder or remove existing entries.
