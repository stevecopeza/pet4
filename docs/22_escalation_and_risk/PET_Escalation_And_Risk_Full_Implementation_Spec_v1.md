# PET Escalation & Risk --- Full Implementation Spec v1.0

Date: 2026-02-26
Related existing docs: docs/15_implementation_blueprint,
docs/27_event_registry

## Goal

Implement a deterministic, idempotent escalation system that converts
detected operational risk into explicit, auditable escalation records
and events, surfaced in UI and advisory outputs.

## Non-Goals (v1)

-   No complex rules DSL
-   No external paging/on-call integrations
-   No auto-muting without an explicit acknowledgement

## Domain Model

### Entities

-   **EscalationRule**
    -   id (UUID)
    -   name
    -   is_enabled
    -   trigger_type (ENUM: SLA_BREACH, SLA_WARNING, ADVISORY_SIGNAL,
        MANUAL)
    -   criteria_json (structured criteria; versioned schema)
    -   severity (ENUM: LOW, MEDIUM, HIGH, CRITICAL)
    -   target_type (ENUM: TEAM, EMPLOYEE, ROLE)
    -   target_id (UUID or well-known role key)
    -   cooldown_minutes (int, optional)
    -   created_at
-   **Escalation** (as implemented in Phase 1)
    -   id (auto-increment bigint)
    -   escalation_id (UUID v4, unique)
    -   source_entity_type (ENUM: ticket, project, customer)
    -   source_entity_id (bigint)
    -   severity (ENUM: LOW, MEDIUM, HIGH, CRITICAL)
    -   status (ENUM: OPEN, ACKED, RESOLVED)
    -   reason (text)
    -   metadata_json (longtext, default '{}')
    -   open_dedupe_key (char(64), nullable, unique — NULL when resolved)
    -   created_by (bigint, nullable)
    -   acknowledged_by (bigint, nullable)
    -   resolved_by (bigint, nullable)
    -   created_at (datetime)
    -   acknowledged_at (datetime, nullable)
    -   resolved_at (datetime, nullable)

    Note: `rule_id` is deferred to Phase 2 (rules engine). Phase 1
    escalations are created via `TriggerEscalationCommand` from the SLA
    bridge listener.

### Domain Invariants

-   An Escalation is immutable once opened; status changes are explicit
    transitions with actor + timestamp.
-   Only one OPEN escalation may exist per dedupe key
    (`source_entity_type|source_entity_id|severity|reason`).
    Resolving an escalation clears its `open_dedupe_key` (set to NULL),
    freeing the slot for future triggers.
-   Acknowledgement does not delete or mutate history; it records a
    transition.
-   Escalations MUST NOT mutate ticket lifecycle state.

### Domain Events (new or confirmed)

-   EscalationTriggeredEvent (already exists; must be dispatched)
-   EscalationAcknowledgedEvent (new)
-   EscalationResolvedEvent (new)

## Application Layer

### Commands

-   TriggerEscalationCommand (system/internal; idempotent)
-   AcknowledgeEscalationCommand (actor-driven)
-   ResolveEscalationCommand (actor-driven)
-   CreateEscalationRuleCommand
-   UpdateEscalationRuleCommand
-   EnableEscalationRuleCommand / DisableEscalationRuleCommand

### Services

-   **EscalationEvaluationService**
    -   evaluateForTicketBreach(ticketId)
    -   evaluateForTicketWarning(ticketId)
    -   evaluateForAdvisorySignal(signalId)
    -   Enforces cooldown + uniqueness
    -   Dispatches EscalationTriggeredEvent on OPEN transition only

### Dispatch Points

-   TicketWarningEvent → trigger_type=SLA_WARNING
-   TicketBreachedEvent → trigger_type=SLA_BREACH
-   AdvisorySignalRaisedEvent → trigger_type=ADVISORY_SIGNAL

## Infrastructure

### Tables / Migrations

-   pet_escalation_rules
    -   indexes: is_enabled, trigger_type, severity
-   pet_escalations
    -   dedupe key for OPEN rows: `open_dedupe_key =
        SHA-256(source_entity_type|source_entity_id|severity|reason)`
    -   unique: open_dedupe_key (nullable — NULL for RESOLVED rows)
    -   indexes: escalation_id (unique), source_entity (type+id),
        status, severity, created_at
-   pet_escalation_transitions (append-only)
    -   escalation_id, from_status (nullable for initial NULL→OPEN),
        to_status, transitioned_by, reason (nullable), transitioned_at
    -   indexes: escalation_id, transitioned_at

### Idempotency & Concurrency

-   **Pre-check:** Before inserting, `TriggerEscalationHandler` reads
    `findOpenByDedupeKey()`. If an OPEN escalation already exists, the
    handler returns its ID immediately (no insert, no event, no
    transition).
-   **DB-level uniqueness:** The `open_dedupe_key` column has a UNIQUE
    constraint. If two concurrent inserts race past the pre-check, the
    DB rejects the loser.
-   **Duplicate-key recovery:** `SqlEscalationRepository::save()` detects
    duplicate-key insert failures and throws `DuplicateKeyException`.
    The handler catches this, re-reads the winning row via
    `findOpenByDedupeKey()`, and returns its ID. No event is dispatched
    and no transition is written for the losing attempt.
-   **Event semantics:** Only the winning insert dispatches
    `EscalationTriggeredEvent`. The loser is a silent recovery.

## Settings / Configuration

Feature flags (config-backed): - pet_escalation_enabled (master) -
pet_escalation_auto_on_sla_warning (default true when enabled) -
pet_escalation_auto_on_sla_breach (default true when enabled) -
pet_escalation_cooldown_default_minutes (default 240)

## API Contract (separate doc required)

-   GET /escalations?status=OPEN&severity=...
-   GET /escalations/`<built-in function id>`{=html}
-   POST /escalations/`<built-in function id>`{=html}/ack
-   POST /escalations/`<built-in function id>`{=html}/resolve
-   GET /escalation-rules
-   POST /escalation-rules
-   PATCH /escalation-rules/{id}

## UI Contract

Admin: - Escalations dashboard (list + filters) - Escalation detail with
transition timeline - Rules manager - Settings

Shortcodes: - \[pet_escalations_my\] - \[pet_escalations_wallboard\]

## Tests

### Unit tests (tests/Unit)
-   No duplicate OPEN under concurrent triggers (in-memory)
-   SLA breach triggers escalation once
-   ACK/RESOLVE appends transitions
-   Dedupe key computation is deterministic
-   Resolve clears dedupe key

### Integration tests (tests/Integration)
-   Migration correctness: columns, unique constraint, nullable from_status
-   Repository round-trip: save/load escalation + transition
-   Trigger idempotency through real SQL path
-   Duplicate-key recovery: handler returns existing ID, no extra event
-   Lifecycle isolation: escalation cannot mutate tickets

## Acceptance Criteria

-   EscalationTriggeredEvent dispatched in real flows
-   No duplicates under concurrency (proven by integration tests)
-   Duplicate-key race handled gracefully with no exception bubbled
-   UI and API complete and role-gated
