# PET Structural Realignment & Hardening Plan v1.1

Date: 2026-02-27\
Status: IMPLEMENTATION-READY\
Scope: Write-side atomicity, concurrency safety, idempotency, migration
determinism, SLA version binding

------------------------------------------------------------------------

# 1. Architectural Position (Corrected)

PET is event-driven with partial event-stream infrastructure. It is NOT
event-sourced.

This plan does NOT convert PET into event sourcing. It introduces: -
Atomic command execution - Deterministic concurrency control - Durable
event logging for recovery - Listener idempotency - Migration parity

------------------------------------------------------------------------

# 2. Non-Negotiable Guarantees

After implementation:

1.  Every command handler executes inside a single DB transaction.
2.  Aggregate save + event append + listener side-effects are atomic.
3.  Core domain events are appended to the event stream.
4.  AcceptQuote is concurrency-safe (row lock required).
5.  Outbox dispatch is concurrency-safe.
6.  CLI and Web activation use an identical migration list.
7.  SlaClockState.slaVersionId binds to actual SLA version.
8.  All listeners are idempotent.
9.  No listener-level try/catch isolation under Option A.

------------------------------------------------------------------------

# 3. Transaction Model (MANDATORY -- OPTION A ONLY)

Transaction boundary MUST wrap:

BEGIN - Load aggregate (SELECT FOR UPDATE if needed) - Mutate
aggregate - Save aggregate - Append domain event to event stream -
Dispatch event (listeners execute inside transaction) COMMIT

If ANY listener throws → ROLLBACK.

Listener isolation (try/catch per listener) is explicitly PROHIBITED.

------------------------------------------------------------------------

# 4. Event Stream Behavior (Clarified)

Event stream is a durable log, NOT the source of truth.

Rules: - append() occurs before dispatch. - event_uuid must be unique. -
Duplicate event_uuid → NO-OP (idempotent return). - Duplicate event_uuid
MUST NOT fail the command. - Idempotency must be enforced at listener
level via existence checks or DB unique keys.

------------------------------------------------------------------------

# 5. Concurrency Hardening

## 5.1 AcceptQuote

AcceptQuoteHandler MUST: - Use SELECT ... FOR UPDATE when loading the
quote. - Execute inside transaction boundary. - Prevent double cascade
under concurrent requests.

## 5.2 Outbox

findDue() MUST: - Lock rows (FOR UPDATE SKIP LOCKED OR status transition
to 'processing'). - External dispatch occurs only after row claim. -
markSent() occurs inside same transaction.

------------------------------------------------------------------------

# 6. SLA Version Binding

When creating SlaClockState: - slaVersionId MUST equal
SlaDefinition.version_number. - Value must never be 0. - Once set,
version is immutable.

Existing clocks must not change version when SLA definition changes.

------------------------------------------------------------------------

# 7. Migration Determinism

A single MigrationRegistry::all() MUST exist.

Both: - plugins_loaded activation - WP-CLI migration command

MUST call the same registry method.

Migration arrays must be byte-identical.

------------------------------------------------------------------------

# 8. Listener Idempotency Requirements

Listeners that create records MUST:

1.  Check existence by deterministic key (quoteId, sourceId, etc.) OR
2.  Rely on DB unique constraint and gracefully ignore duplicate
    violation.

FeedProjectionListener MUST prevent duplicate entries for same
event_uuid.

------------------------------------------------------------------------

# 9. Required Test Matrix

Must exist before release:

-   Concurrent AcceptQuote → exactly one project
-   Listener exception → full rollback
-   Duplicate event_uuid → no duplicate side-effects
-   Duplicate event dispatch → no duplicate projections
-   Two outbox workers → no duplicate external calls
-   Migration registry parity assertion
-   SLA version binding correctness

All tests must FAIL before implementation and PASS after.

------------------------------------------------------------------------

# 10. Explicit Prohibitions

-   No redesign of aggregates.
-   No REST contract changes.
-   No destructive schema modifications.
-   No listener isolation under transactional model.
-   No silent swallow of integrity errors.

------------------------------------------------------------------------

End of Plan v1.1
