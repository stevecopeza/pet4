# PET Integrity Principles
Version: v1.0
Date: 2026-04-22
Author: Steve Cope / AI Agent

---

## Purpose

This document extracts the integrity and correctness principles that underpin PET's
architecture. These are not aspirational guidelines — they describe what the system
already does well and what every new feature must continue to do.

The principles were crystallised through a dual-pass security and integrity audit
(2026-04-22). The audit record is at `docs/security_posture/01_assessment_2026_04_22.md`.

---

## Principle 1: Domain Entity as Truth Guardian

**The domain entity is the sole legitimate path for mutating authoritative state.**

PET uses domain entities (not SQL updates in handlers) to enforce business rules. Every
state transition, status change, and derived computation must go through the entity,
which validates the change and decides whether to allow it.

**In practice:**
- Quote state changes happen via `$quote->accept()`, `$quote->send()` etc. — not via
  direct `$wpdb->update('wp_pet_quotes', ['state' => 'accepted'], ...)`.
- The state machine is in the entity, not in the service layer. This means it cannot
  be bypassed by a different handler calling the same table.
- Any handler that writes to a domain table via raw `$wpdb` is bypassing this guarantee
  and must be treated as a potential invariant violation.

**Violation signal:** if you find yourself writing `$wpdb->update()` in a handler for a
domain table, ask "which domain entity should own this change?"

---

## Principle 2: Terminal States Are Immutable

**Once a domain object reaches a terminal state, it cannot be mutated or re-entered.**

Examples in PET:
- Quote: ACCEPTED, REJECTED, ARCHIVED are terminal. No transition out exists.
- Advisory reports: published reports have no update or delete path.
- Time entries: no edit path exists; corrections create new entries.

**The implementation pattern for terminal states:**
1. State machine `canTransitionTo()` blocks illegal transitions at the entity level.
2. Repository `save()` uses `INSERT` for immutable types, not `INSERT ... ON DUPLICATE
   KEY UPDATE`.
3. REST controller has no PATCH/PUT endpoint for immutable resources.

All three layers must agree. A state machine in the entity is not sufficient if the
repository silently overwrites on save, or if a REST endpoint allows direct state updates.

---

## Principle 3: Truth Drift Is Worse Than a Visible Error

**An operation that partially succeeds is more dangerous than one that fully fails.**

"Truth drift" occurs when the system's representation of what happened diverges from
reality. Examples:
- Quote accepted, but delivery tickets not created → system says "accepted" but delivery
  layer has nothing to execute.
- Outbox event dispatched externally, but not marked sent → same event re-dispatched.
- Time entry corrected, but original not linked → correction is orphaned.

Partial failure is silent. A visible error is recoverable.

**The implementation response to truth drift:**
- Wrap state transitions and their side effects in a single database transaction. If the
  side effects fail, the state transition rolls back.
- For cases where a long transaction is impractical, implement idempotent recovery
  methods (`ensureTicketsProvisioned(Quote $quote)`) that can be safely replayed.
- For external integrations (where transactions don't help), use the outbox pattern with
  idempotency keys so the external system can safely receive duplicates.

---

## Principle 4: Derived Truth Is Append-Only

**Generated data (reports, projections, activity events) is never overwritten.**

PET generates advisory reports, activity feed events, and SLA state snapshots. These are
derived from authoritative sources at a point in time. They must not be edited after
generation — doing so severs the audit chain.

**The implementation pattern:**
- Repository `save()` is INSERT-only (no ON DUPLICATE KEY UPDATE).
- No PATCH or PUT REST endpoint exists for derived records.
- If a generated value is wrong, the correct action is to generate a new version — not
  to overwrite the old one.

**Why this matters:** if reports can be overwritten, you cannot answer "what did the
system say at time T?" Advisory reports and billing data must be auditable.

---

## Principle 5: Concurrency Requires Explicit Guards

**Any shared mutable state that multiple processes can read and write needs an explicit
concurrency control.**

PET runs four cron jobs every five minutes. WordPress cron fires on page load. Two cron
invocations can execute concurrently if two requests arrive before the first finishes.

**Patterns in use:**
- SLA clock: `SELECT ... FOR UPDATE` on the clock state row before evaluation. Correct
  for row-level concurrency.
- Event outbox: `claim()` sets `next_attempt_at` forward in a transaction before the
  external call. Correct for preventing concurrent dispatch of the same row.

**Pattern needed but not yet universally applied:**
- Method-level mutex for cron jobs that iterate over a full dataset (rather than single
  rows). Use a WordPress transient as a process lock:
  ```php
  if (get_transient('pet_sla_check_running')) return;
  set_transient('pet_sla_check_running', true, 300);
  try { /* ... */ } finally { delete_transient('pet_sla_check_running'); }
  ```

The row-level lock protects individual records. The method-level lock protects against
duplicate full-dataset passes.

---

## Principle 6: External Effects Require Idempotency Keys

**Any event dispatched to an external system must be recoverable without duplicate effect.**

Outbox dispatch follows at-least-once semantics by necessity: the dispatch can succeed
but the acknowledgement can fail. The external system must be able to receive the same
event twice and only act on it once.

**Implementation requirements:**
- Each outbox row has a unique `event_id` that is stable across retries.
- A unique constraint on `(event_id, destination_type)` prevents multiple rows for the
  same event.
- External endpoints that receive PET events should accept an idempotency key header
  and deduplicate on it.

**The outbox unique constraint is the database-level backstop.** Even if the application
enqueues the same event twice, the DB will reject the duplicate insert.

---

## Principle 7: Scoping Is a Trust Boundary

**Every data-reading operation must know whose data it is permitted to return.**

PET applies auth at the endpoint level (is this user logged in? do they have
manage_options?) but not at the data level. Repository `findAll()` methods return data
across all customers.

**Confirmed model (2026-04-22): single-tenant, single MSP.** All admin users are
trusted staff of the same business. Unscoped `findAll()` is acceptable under this model.
No `tenant_id` or automatic `customer_id` scoping is required at this time.

**If the model changes**, the correct implementation pattern is:
- Repository methods accept an optional scope parameter: `findAll(?int $customerId = null)`
- Portal-facing endpoints pass `$authenticatedCustomerId`; admin endpoints pass null (see all)
- Enforcement at the repository level (cannot be accidentally omitted by a new endpoint)

**Triggers that require revisiting this:**
- A second independent customer group is added (different MSP business sharing the instance)
- Any portal user is restricted from seeing other customers' data
- An account manager is restricted to only their assigned accounts

See `docs/security_posture/01_assessment_2026_04_22.md` for the full multi-tenancy
decision record and cost of each scenario.

---

## Principle 8: Environment Guards Protect Production

**Any operation that writes demo, test, or non-reversible data to a database must be
gated on the environment.**

The pattern established by `wp pet migrate`:
```php
$env = \getenv('PET_ENV') ?: \getenv('WP_ENV') ?: '';
if (!\in_array($env, ['local', 'development', 'dev'], true)) {
    // refuse
}
```

Must be applied to every WP-CLI command that:
- Seeds demo data
- Purges data
- Resets state
- Runs performance tests against real tables

The threat is not only human error. CI/CD pipelines without `WP_ENV` set, shared hosting
environments, and automated deploy scripts are all realistic non-human-error vectors.

---

## Quick Reference: Correctness Checklist for New Features

When building a new domain feature, verify:

| Question | What to check |
|---|---|
| Does a terminal state exist? | Is it blocked from mutation in the entity AND the repository? |
| Are there side effects after a state transition? | Are they in the same transaction, or idempotently recoverable? |
| Does it write to an external system? | Is there an outbox with a unique constraint and an idempotency key? |
| Does it read a list of records? | Is the list scoped to the authenticated user's permitted data? |
| Does it generate derived data? | Is the repository INSERT-only with no overwrite path? |
| Is it a WP-CLI command that writes data? | Does it have a PET_ENV guard? |
| Does it run on a cron? | Is there a mutex to prevent concurrent double-runs? |
