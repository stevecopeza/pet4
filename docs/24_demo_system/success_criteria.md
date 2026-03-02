# PET Demo Success Criteria v1.1

Version: 1.1\
Date: 2026-02-14\
Status: Binding (Demo System)

## Purpose

Define measurable, testable criteria for the PET demo system,
including: - Option **C**: "Demo everything" (breadth + depth) as the
primary goal - Controlled fallback to Option B/A when C is not
achievable in the current environment

## Definitions

-   **Demo run**: a single execution of `seed_full` producing a
    `seed_run_id` and a coherent demo dataset.
-   **Demo dataset**: all entities created or updated by demo seed steps
    and tracked via the Demo Seed Ledger.
-   **Workflow transition**: any domain state change (e.g., Quote
    Draft→Accepted, Time Draft→Submitted).

## Success Levels

### PASS (Option C achieved)

All conditions must be true: 1. **Seed**: `POST /system/seed_full`
returns **201** with a valid `seed_run_id`. 2. **Purge**:
`POST /system/purge` returns **200** and removes all purgeable
demo-owned entities without touching user-owned data. 3. **Readiness**:
Preflight `GET /system/pre-demo-check` returns `overall=PASS` and all
required checks are PASS. 4. **Breadth**: Every "Must Show" capability
listed in `PET_Demo_Capability_Matrix_v1_1.md` has at least one coherent
instance seeded and visible via API/UI. 5. **Depth**: Every "Must
Transition" workflow listed in the Capability Matrix completes
successfully **without domain exceptions**. 6. **Determinism**: Two
consecutive seed runs (seed→purge→seed) produce the same capability
counts and the same named "anchor artifacts" (see Anchor Artifacts). 7.
**Safety**: No step uses domain exceptions as control flow; all
transitions are preceded by readiness validation.

### PARTIAL (Fallback to Option B)

Allowed if: - Seed returns **201** - Breadth is met - **At least 2**
end-to-end workflows complete (defined in Matrix as "Preferred Depth
Workflows") - Remaining transitions may be left in the closest legal
earlier state (e.g., quote remains Draft/Ready)

### FAIL (Fallback to Option A)

Triggered if any of the following occur: - Seed cannot return 201
reliably (e.g., intermittent 422/500) - Preflight returns overall !=
PASS - Any demo-critical screen/workflow throws an unhandled exception -
Purge cannot safely execute (returns non-200 or risks non-demo data)

## Anchor Artifacts (Deterministic "named" demo objects)

Seed must always produce the following named artifacts (exact names),
used by tests and demo script: - Customer: `DEMO Customer - Acme` -
Site: `DEMO Site - Acme HQ` - Contact: `DEMO Contact - Jane Doe` - Team:
`DEMO Team - Delivery` - Employee: `DEMO Employee - Alex Smith` - Quote
Draft: `DEMO Quote - Q2 (Draft)` - Quote Accepted:
`DEMO Quote - Q1 (Accepted)` - Project Active:
`DEMO Project - P1 (Active)` - Ticket: `DEMO Ticket - T1 (SLA)` - Time
Entry Submitted: `DEMO Time - W1 (Submitted)`

## Non-Negotiable Principles

-   Domain invariants remain authoritative and unchanged.
-   Demo correctness is achieved through **readiness gates +
    deterministic autofill**, never by weakening validation.
-   Immutability rules remain intact: accepted quotes, submitted time,
    domain events are not edited or deleted.

## Reporting Requirements

Seed response must include: - `seed_run_id` - step-by-step results
(applied/skipped/degraded) - counts per entity type created - any
degraded outcomes with reasons and impacted anchor artifacts

Purge response must include: - counts purged / archived / skipped - list
of skipped items (seed ledger ids) with reason (e.g., user-touched)
