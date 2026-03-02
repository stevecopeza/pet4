# PET Demo PreFlight Check Spec v1.1

Version: 1.1\
Date: 2026-02-14\
Status: Binding (Pre-Demo Viability)

## Purpose

Provide a deterministic, actionable pre-demo viability report to prevent
demo activation when requirements are unmet.

## Endpoint

`GET /system/pre-demo-check` (see Seed Contract for response format)

## Required Checks (MUST)

### Database Capability

-   `db.connection` --- can connect and query
-   `db.tables_present` --- required demo tables exist
-   `db.columns_present` --- required columns exist
-   `db.indexes_present` --- required unique/index constraints exist
    where needed for concurrency

### Required Table/Column Set

The preflight must check at minimum: - core entity tables used by seed
(customers/sites/contacts/quotes/projects/tickets/time) - SLA tables
(e.g., sla clock state) if SLA is in demo scope - demo seed ledger table
(if purge safety is enabled)

### Domain Capability

-   `domain.quote.readiness` --- can validate readiness for a
    constructed draft quote
-   `domain.quote.accept` --- can accept a fully ready quote in a
    dry-run transaction
-   `domain.time.submit` --- can submit a time entry in a dry-run
    transaction
-   `domain.sla.evaluate` --- can initialize and evaluate SLA twice
    idempotently

### Projection/Derived Capability

-   `projection.feed.writable` --- feed/projection tables accept events
-   `projection.readable` --- key derived views return data for anchor
    artifacts

## Execution Requirements

-   Preflight must be fast (target < 2 seconds) and safe (no persistent
    side effects).
-   Dry-run checks must run in a transaction and rollback.

## Output Semantics

-   `overall=PASS` only if all MUST checks pass.
-   `overall=FAIL` if any MUST check fails.
-   `overall=PARTIAL` only allowed if explicitly enabled (not
    recommended for client demo).

## Example Check Items

``` json
{
  "key": "db.columns_present.quotes.payment_schedule",
  "status": "PASS",
  "detail": "Payment schedule persistence available"
}
```

## Hard-Block Rule

If `overall != PASS`, the UI must: - prevent "Activate Demo" and "Seed
Full" actions - show the failed checks and actionable detail
