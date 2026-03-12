# PET – Domain Events Schema

## Purpose of this Document
This document defines the **authoritative schema and rules** for domain events in PET.

Domain events are the immutable facts from which:
- KPIs are derived
- Activity feeds are rendered
- Audits are reconstructed

If events are inconsistent, everything above them is untrustworthy.

---

## Core Principles

- Events are append‑only
- Events are immutable
- Events represent facts, not intentions
- Events are domain‑level, not UI‑level

---

## Event Table

### `domain_events`

The canonical storage for all events.

Minimum columns:

- `id` (PK)
- `event_type` (string, namespaced)
- `occurred_at` (timestamp)
- `recorded_at` (timestamp)
- `actor_employee_id` (nullable for system events)
- `context_type` (string)
- `context_id` (FK reference id)
- `payload` (JSON)
- `schema_version` (int)

No updates. No deletes.

---

## Event Types

Event types are **explicit and namespaced**.

Examples:
- `lead.created`
- `lead.qualified`
- `opportunity.classified`
- `quote.sent`
- `quote.accepted`
- `project.variance_detected`
- `time_entry.submitted`
- `sla.breached`

Rules:
- Event names are stable
- Renaming creates a new event type

---

## Context Model

Every event must have **primary context**.

Primary context examples:
- Customer
- Project
- Ticket
- Quote

Additional context may be included in payload.

Events without resolvable context are invalid.

---

## Payload Rules

The payload:
- Is JSON
- Is versioned
- Contains only factual data

Rules:
- No derived values
- No references that cannot be resolved later
- No UI formatting

Payload schemas evolve via `schema_version`.

---

## Schema Versioning

Rules:
- Each event type has its own schema versions
- Schema versions are immutable
- Consumers must handle old versions

Historical events are never reinterpreted.

---

## Idempotency

To prevent duplication:

- Events may include an optional `idempotency_key`
- Replays with the same key are ignored

Idempotency is enforced at the domain layer.

---

## Event Emission Rules

Events are emitted:
- After successful state transitions
- Within transaction boundaries

If an event cannot be recorded, the operation fails.

---

## Consumption Patterns

Events are consumed by:
- KPI calculators
- Activity feed projectors
- Audit tools

Consumers must:
- Be deterministic
- Be idempotent
- Never mutate events

---

## Retention

Rules:
- Events are retained indefinitely
- Archival is logical (visibility only)

Storage growth is an accepted trade‑off for trust.

---

## What This Prevents

- KPI ambiguity
- Audit gaps
- Silent behaviour changes
- Irreversible data corruption

---

**Authority**: Normative

This document defines PET’s event backbone.

