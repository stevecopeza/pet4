# PET SLA & Work Orchestration Staging Validation Checklist v1.0

Date: 2026-02-26

## Purpose

Define mandatory staging validation before production release.

------------------------------------------------------------------------

## 1. Migration Validation

-   Fresh install migration success
-   Upgrade from N-2 version success
-   No schema drift
-   Unique constraints enforced

------------------------------------------------------------------------

## 2. SLA Validation

-   Active → Warning transition verified
-   Warning → Breach transition verified
-   Double evaluation does not double fire
-   Concurrent evaluation safe

------------------------------------------------------------------------

## 3. Projection Validation

-   TicketCreatedEvent fired twice → one WorkItem
-   Retry safe
-   Concurrent listener safe
-   Unique constraint enforced

------------------------------------------------------------------------

## 4. Assignment Invariants

-   Exactly one of team/employee set
-   Illegal dual assignment rejected
-   Pull action atomic

------------------------------------------------------------------------

## 5. Priority Stability

-   Equal scores produce stable order
-   SLA proximity increases score deterministically

------------------------------------------------------------------------

## 6. Scheduler Load Test

-   Batch processing capped
-   No long locks
-   No duplicate events under concurrent cron

------------------------------------------------------------------------

## Final Gate

Release blocked unless:

-   All tests pass
-   No duplicate artifacts observed
-   No lock escalation
