# PET Demo Seed Contract v1.1

Version: 1.1\
Date: 2026-02-14\
Status: Binding (API + Seed Behavior)

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
