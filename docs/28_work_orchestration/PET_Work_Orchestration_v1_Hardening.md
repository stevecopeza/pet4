# PET Work Orchestration v1 Hardening Specification

Date: 2026-02-26

## Purpose

Define strict boundaries and invariants for Work Orchestration v1.
Prevent projection duplication, assignment ambiguity, and queue
instability.

This document is ADDITIVE to:

-   docs/07_work_orchestration/08_Work_Orchestration_Queues_and_Assignment_v1.md

------------------------------------------------------------------------

## 1. WorkItem Identity

WorkItem is a projection of a source entity.

Minimum identity constraints:

-   source_type
-   source_id
-   context_version (if versioned source)

DB MUST enforce:

UNIQUE(source_type, source_id, context_version)

This prevents duplicate projections.

------------------------------------------------------------------------

## 2. Projection Flow

``` mermaid
sequenceDiagram
    participant Ticket
    participant Listener
    participant WorkItem
    Ticket->>Listener: TicketCreatedEvent
    Listener->>WorkItem: Create projection (idempotent)
```

Listener MUST:

-   Check for existing projection
-   Be safe under duplicate events
-   Be safe under retries

------------------------------------------------------------------------

## 3. Assignment Invariants

Exactly ONE of:

-   assigned_team_id
-   assigned_employee_id

must be set.

Rules:

-   Team assignment = queue-visible, unclaimed
-   Pull action converts team → employee atomically
-   Manager reassignment must respect permission boundaries

------------------------------------------------------------------------

## 4. Queue Membership

Queue derivation MUST be deterministic.

Queue selection rules must be codified, e.g.:

-   Derived from ticket category
-   Or derived from owning team
-   Or explicit mapping table

No runtime guessing.

------------------------------------------------------------------------

## 5. Priority Scoring

PriorityScoringService MUST:

-   Use deterministic inputs
-   Include SLA proximity
-   Include VIP multiplier (if defined)
-   Include stable tie-break (created_at ASC)

Ordering must be stable across repeated calls.

------------------------------------------------------------------------

## 6. Required Tests

### Projection Tests

-   Duplicate TicketCreatedEvent produces one WorkItem
-   Retry safe
-   Concurrent safe

### Assignment Tests

-   Pull converts team → employee
-   Illegal dual assignment rejected

### Priority Tests

-   Stable ordering for equal scores
-   SLA proximity increases score predictably

------------------------------------------------------------------------

## Acceptance Criteria

-   No duplicate projections
-   Deterministic queue ordering
-   Strict assignment invariants
-   Backward-compatible migration safety
