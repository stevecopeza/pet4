# PET Demo-Critical Areas --- Full Implementation Plan v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Scope

This plan covers full implementation (Domain, Application,
Infrastructure, Settings/Config, API, UI/Shortcodes/Admin) for:

1.  Escalation & Risk (docs/15)
2.  Support Helpdesk (docs/24)
3.  Advisory Layer (docs/18)
4.  People Resilience (docs/16)

Non-negotiable constraints (PET baseline):

-   Domain rules enforced in Domain layer only
-   Immutable history; corrections are additive
-   Event-backed behaviour; listeners/projections derive views
-   Backward compatibility; users may skip versions
-   Forward-only migrations; no down migrations
-   UI is a projection surface; no business logic

## Delivery Strategy

### Phase A --- "Operational Feedback Loop" (demo narrative ready)

-   Escalation triggers from SLA + risk signals
-   Helpdesk overview shows real data + SLA timers + escalations
-   Advisory "QBR Snapshot" aggregates key metrics + risks
-   People Resilience "SPOF" signals visible and tied to escalation/risk

### Phase B --- Governance & Controls

-   Settings to enable/disable subsystems (feature flags)
-   Role-gated UI actions and screens
-   Audit trails for escalations, acknowledgements, and advisory outputs

### Phase C --- Hardening

-   Concurrency and idempotency tests
-   Projection rebuilds, backfills, and migration tolerance
-   Performance bounds (batch sizes, indexes, pagination)

## Common Requirements (apply to all 4 areas)

### 1) Settings & Feature Flags

Introduce config-backed flags (default OFF on upgrade): -
pet_escalation_enabled - pet_helpdesk_enabled - pet_advisory_enabled -
pet_people_resilience_enabled

Also per-area sub-flags (detailed in each area plan).

### 2) API Contracts

For each area: - Read-only endpoints for dashboards and shortcodes -
Minimal command endpoints for acknowledgement/assignment where
required - Consistent pagination, filters, and permission checks - API
registry updated

### 3) UI Surfaces

-   Admin pages for configuration and governance
-   Shortcodes for operational views (team wallboards, "my" views)
-   Dashboard cards that summarize state (read-only, clickable to detail
    pages)

### 4) Event Backbone Alignment

-   Each subsystem has explicit domain events
-   Outbound side effects are idempotent (outbox or equivalent)
-   Projections derive read models used by UI

### 5) Tests

-   Unit tests for domain invariants and state machines
-   Integration tests for handlers, repositories, migrations
-   "No duplicate" tests (events, projections, escalations)
-   "Upgrade from older schema" tests where applicable

## Documentation Needed (what must be written before implementation)

Implementation should not start until these docs exist (as ADDITIVE docs
in docs/ToBeMoved):

1.  **Per-area Implementation Spec** (Domain + Application +
    Infrastructure + UI + Settings)
2.  **Per-area API Contract** (endpoints, payloads, permissions, error
    modes)
3.  **Per-area Data Model + Migrations** (tables, indexes, constraints,
    versioning)
4.  **Per-area Event Registry Addendum** (new/updated events, dispatch
    points, listeners)
5.  **Per-area UI Contract** (screens, read-only vs commands, role
    gating)
6.  **Rollout & Feature Flag Plan** (activation order, monitoring,
    rollback)
7.  **Demo Seed/Fixture Plan** (what demo data is needed to show the
    story)

The next four documents in this bundle are the per-area specs that
define exactly the above.
