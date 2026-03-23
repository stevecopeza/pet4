# System API Contract

## Overview
System-level endpoints for health checks, diagnostics, and configuration.

## Endpoints

### 1. Pre-Demo Flight Check

**GET /pet/v1/system/pre-demo-check**

Validates system readiness for Demo Engine activation.

**Permissions:** `manage_options` (Admin only)

**Response (200 OK):**
```json
{
  "sla_automation": "PASS",
  "event_registry": "PASS",
  "projection_handlers": "PASS",
  "quote_validation": "PASS",
  "overall": "PASS"
}
```

**Failure Response:**
If any check fails, the status code is still 200, but the JSON will indicate FAIL.
Hard-blocking logic on the client side or activation logic should respect the `overall` field.

**Checks Performed:**
- **sla_automation**: Verifies `sla_clock_state` table and `SlaAutomationService`.
- **event_registry**: Verifies all critical events are registered.
- **projection_handlers**: Verifies projection listeners are active.
- **quote_validation**: Verifies DB schema for quote invariants (`sku`, `role_id`, `type`).

### 2. Clean Demo Baseline

**POST /pet/v1/system/demo/clean-baseline**  
Alias: **POST /pet/v1/system/clean-demo-baseline**

Purges all currently tracked active seed runs (newest first), then performs one fresh `demo_full` seed run.

**Permissions:** `manage_options` (Admin only)  
**Required param:** `confirm=CLEAN_DEMO_BASELINE`

**Success Response (201):**
```json
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

**Contract failure response (422):**
```json
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

`contract.violations` is authoritative for post-baseline invariant failures.

### 3. Demo Environment Health

**GET /pet/v1/system/demo/health**

Read-only readiness signal for demo operators.

**Permissions:** `manage_options` (Admin only)

**Response (200):**
```json
{
  "readiness_status": "GREEN",
  "readiness_reasons": [
    "no_immediate_readiness_warnings"
  ],
  "seed": {
    "active_seed_run_id": "uuid",
    "tracked_runs_count": 1,
    "active_runs_count": 1
  },
  "integrity": {
    "duplicate_employee_emails": 0,
    "duplicate_skill_pairs": 0,
    "duplicate_certification_pairs": 0
  },
  "flags": {
    "has_integrity_violation": false,
    "has_contamination_risk": false
  }
}
```

`readiness_reasons` is the canonical machine-readable explanation for the current status.

### 4. Seed Registry Diagnostics

**GET /pet/v1/system/demo/diagnostics**

Read-only deep diagnostics for tracked seed runs and registry integrity.

**Permissions:** `manage_options` (Admin only)

**Response (200):**
```json
{
  "runs": [
    {
      "run_id": "uuid",
      "status": "tracked",
      "registry_row_count": 1895
    }
  ],
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

`registry_summary.active_runs_count` and health `seed.active_runs_count` are intended to remain semantically aligned.
