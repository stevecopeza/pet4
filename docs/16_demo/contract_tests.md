# PET Demo Contract Tests v1.1

Version: 1.1\
Date: 2026-02-14\
Status: Binding (Automated Proof of Demo Viability)

## Purpose

Define the mandatory automated tests that prove Option C is achievable
and stable.

## Test Categories

### A) API Contract Tests (Must)

1.  Preflight returns 200 and overall PASS in supported environment
2.  Seed full returns 201 and overall PASS
3.  Seed response includes anchors for required artifacts
4.  Purge returns 200 and reports purged/skipped/archived

### B) Workflow Depth Tests (Must Transition)

1.  Quote Q1:
    -   exists as Draft initially
    -   readiness gates satisfied
    -   accepts without exception
    -   payment schedule exists and sums to total
2.  Project P1:
    -   created from accepted Q1
    -   sold constraints present (if implemented)
3.  SLA T1:
    -   clock initialized
    -   evaluate is idempotent (run twice, same resulting state)
4.  Time W1:
    -   draft created
    -   submitted successfully
    -   immutable after submit (attempted edit rejected or requires
        compensating entry)

### C) Purge Safety Tests (Must)

1.  Purge removes ledger-tracked demo data for the seed_run_id
2.  Purge does not remove non-demo data
3.  User-touched entity is skipped (simulated by modifying one demo
    entity then purging)

## Reference Implementation Expectations

-   Prefer Playwright e2e for API-level checks where already
    established.
-   Add integration tests for domain invariants where faster/more
    deterministic.

## Suggested Playwright Scenarios

-   `tests/e2e/api-seed-full.spec.ts`
    -   call pre-demo-check
    -   call seed_full
    -   assert 201 and anchors exist
    -   call purge and assert 200

## Mermaid: Test Flow

``` mermaid
flowchart TD
  A[Preflight PASS] --> B[Seed Full 201]
  B --> C[Validate Anchors]
  C --> D[Validate Workflows]
  D --> E[Purge 200]
  E --> F[Verify Cleanup + Safety]
```
