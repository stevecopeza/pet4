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
