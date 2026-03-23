# PET Demo Seed Contract v1.2

Version: 1.2\
Date: 2026-03-23\
Status: Binding (API + Seed/Purge/Health Behavior)

## Purpose

Define API contracts and behavior for demo seed and purge, including
structured outcomes and deterministic anchors.

## Endpoints

### 1) GET /system/pre-demo-check

**Purpose:** Validate environment + schema + domain capabilities
required for demo.

**Success Response (HTTP 200):**

``` json
{
  "overall": "PASS",
  "checks": [
    {
      "key": "db.tables_present",
      "status": "PASS",
      "detail": "All required tables exist"
    }
  ]
}
```

**Failure Response (HTTP 200):** - Still returns 200, but `overall` will
be `FAIL`. - Demo activation must be blocked when overall != PASS.

### 2) POST /system/seed_full

**Purpose:** Create a complete demo dataset.

**Success (HTTP 201):**

``` json
{
  "seed_run_id": "uuid",
  "overall": "PASS",
  "anchors": {
    "customer_id": "…",
    "quote_q1_id": "…",
    "project_p1_id": "…"
  },
  "steps": [
    {
      "step": "seed.customers",
      "status": "APPLIED",
      "created": 1,
      "updated": 0,
      "degraded": 0
    }
  ],
  "counts": {
    "customers": 1,
    "leads": 6,
    "quotes": 4,
    "projects": 1,
    "tickets": 1,
    "time_entries": 3
  }
}
```

**Partial Success (HTTP 201):** - Allowed only for Option B fallback or
internal staging. - `overall` will be `PARTIAL`, and failures appear in
`steps[].issues`.

**Domain Failure (HTTP 422):** Seed must return structured error and not
white-screen.

``` json
{
  "seed_run_id": "uuid",
  "overall": "FAIL",
  "error": "domain_exception",
  "message": "Quote must have a payment schedule.",
  "step": "quotes.accept_q1",
  "entity": {
    "type": "quote",
    "key": "Q1"
  }
}
```

### 3) POST /system/purge

**Purpose:** Safely purge demo data using the demo seed ledger.

**Success (HTTP 200):**

``` json
{
  "seed_run_id": "uuid",
  "overall": "PASS",
  "purged": 152,
  "archived": 3,
  "skipped": 2,
  "skipped_items": [
    {
      "ledger_id": 101,
      "reason": "user_touched"
    }
  ]
}
```

### 4) POST /system/demo/clean-baseline (alias: /system/clean-demo-baseline)

**Purpose:** Purge all active tracked seed runs, then seed exactly one fresh baseline run.

**Required Param:** `confirm=CLEAN_DEMO_BASELINE`

**Success (HTTP 201):**
``` json
{
  "operation": "clean_demo_baseline",
  "overall": "PASS",
  "seed": {
    "seed_run_id": "uuid"
  },
  "registry": {
    "active_seed_runs": 1
  },
  "contract": {
    "violations": []
  }
}
```

**Contract Failure (HTTP 422):**
``` json
{
  "operation": "clean_demo_baseline",
  "overall": "FAIL",
  "error": "post_baseline_contract_violation",
  "contract": {
    "violations": [
      "expected_single_active_run"
    ]
  }
}
```

### 5) GET /system/demo/health

**Purpose:** Read-only readiness classification for operators.

**Success (HTTP 200):**
``` json
{
  "readiness_status": "GREEN",
  "readiness_reasons": [
    "no_immediate_readiness_warnings"
  ],
  "seed": {
    "active_runs_count": 1,
    "tracked_runs_count": 1
  },
  "integrity": {
    "duplicate_employee_emails": 0,
    "duplicate_skill_pairs": 0,
    "duplicate_certification_pairs": 0
  }
}
```

### 6) GET /system/demo/diagnostics

**Purpose:** Read-only deep diagnostics of tracked runs and registry health.

**Success (HTTP 200):**
``` json
{
  "runs": [],
  "integrity_issues": [
    {
      "type": "duplicate_employee_emails",
      "count": 1,
      "severity": "high"
    }
  ],
  "registry_summary": {
    "runs_count": 2,
    "active_runs_count": 2,
    "total_registry_rows": 3000,
    "rows_linked_to_missing_entities": 12
  }
}
```

## Consolidation Invariants (Operational)

- Clean baseline PASS implies `registry.active_seed_runs == 1` and empty `contract.violations`.
- Health `seed.active_runs_count` and diagnostics `registry_summary.active_runs_count` must remain semantically aligned.
- Duplicate integrity classes used by readiness and diagnostics include:
  - `duplicate_employee_emails`
  - `duplicate_skill_pairs`
  - `duplicate_certification_pairs`

## Idempotency and Determinism

-   Re-running `seed_full` without `purge`:
    -   MUST either refuse with a clear error (409) **or**
    -   create a new run in parallel with a new `seed_run_id` and
        distinct ledger entries.
-   Recommended: allow multiple runs; purge must target one
    `seed_run_id`.

## Required Headers / Auth

-   Use existing PET auth pattern. Seed endpoints must be admin-only.
-   No UI-layer business logic: endpoints call application services
    only.

## Response Fields (Required)

-   `seed_run_id` (uuid)
-   `overall` (PASS|PARTIAL|FAIL)
-   `steps[]` with status and counts
-   `counts` aggregated totals
-   `anchors` (named anchor ids; absent only if FAIL before anchors
    created)
