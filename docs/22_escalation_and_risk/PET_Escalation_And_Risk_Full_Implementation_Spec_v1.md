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
-   **Escalation**
    -   id (UUID)
    -   rule_id (UUID, nullable for manual)
    -   source_type (ENUM: TICKET, PROJECT, CUSTOMER, ADVISORY_SIGNAL)
    -   source_id (UUID)
    -   severity
    -   status (ENUM: OPEN, ACKED, RESOLVED)
    -   opened_at
    -   acked_at (nullable)
    -   resolved_at (nullable)
    -   opened_by (system/manual actor)
    -   acked_by (actor, nullable)
    -   resolution_note (immutable note record id; optional)

### Domain Invariants

-   An Escalation is immutable once opened; status changes are explicit
    transitions with actor + timestamp.
-   A given rule may not open duplicate OPEN escalations for the same
    source within cooldown window.
-   Acknowledgement does not delete or mutate history; it records a
    transition.

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
    -   dedupe key for OPEN rows: open_dedupe_key =
        hash(rule_id+source_type+source_id)
    -   unique: open_dedupe_key
    -   indexes: status, severity, opened_at, source_type, source_id
-   pet_escalation_transitions (append-only)
    -   escalation_id, from_status, to_status, actor_id, occurred_at,
        note
    -   indexes: escalation_id, occurred_at

### Idempotency & Concurrency

-   Use transaction + SELECT ... FOR UPDATE when opening escalation for
    a source/rule.
-   Enforce open_dedupe_key uniqueness; on conflict, no-op.

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

-   No duplicate OPEN under concurrent triggers
-   SLA breach triggers escalation once
-   ACK/RESOLVE appends transitions

## Acceptance Criteria

-   EscalationTriggeredEvent dispatched in real flows
-   No duplicates under concurrency
-   UI and API complete and role-gated
