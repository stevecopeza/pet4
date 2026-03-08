# PET Lifecycle Integration Contract --- Advisory Layer v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Purpose

Define when advisory signals and reports exist in the lifecycle of
operational truth, including render/creation/mutation rules, prohibited
behaviours, and stress-test scenarios.

This document is REQUIRED prior to implementation.

------------------------------------------------------------------------

## Parent Entities

Advisory derives from: - Tickets, Projects, Time, SLAs, Escalations,
People Resilience (signals) Advisory MUST NOT mutate any parent entity
state.

------------------------------------------------------------------------

## 1) Render Rules

### Advisory Dashboard MUST render when

-   pet_advisory_enabled == true
-   viewer has manager/exec scope

### Report Generator MUST render when

-   pet_advisory_enabled == true
-   pet_advisory_allow_manual_generation == true
-   viewer has manager scope

### Report Viewer MUST render when

-   report exists and viewer has scope
-   must be read-only, printable

### Advisory MUST NOT render when

-   pet_advisory_enabled == false
-   schema prerequisites missing → fail fast with clear admin error, not
    fatal

------------------------------------------------------------------------

## 2) Creation Rules

### AdvisorySignal MUST be created when

-   pet_advisory_enabled == true
-   AdvisoryGenerator detects a condition per documented signal types
-   Signals are inserted immutably (no updates)

### AdvisoryReport MUST be created when

-   pet_advisory_enabled == true
-   A user explicitly requests generation (manual)
-   (optional) scheduler requests generation and scheduled flag is
    enabled

### AdvisoryReport MUST NOT be created when

-   pet_advisory_enabled == false
-   user lacks permission
-   report generation endpoint is called in "preview" mode (unless
    explicitly implemented)

Idempotency rule: - generating same report type + period creates a new
version (append-only) - must not overwrite existing versions

------------------------------------------------------------------------

## 3) Mutation Rules

Signals: - immutable; no updates or deletes

Reports: - immutable; regeneration creates new version - optional
publish action transitions report status, but must not modify
content_json (publish references an existing version)

No advisory write action may mutate operational truth.

------------------------------------------------------------------------

## 4) Prohibited Behaviours (must NOT happen)

-   MUST NOT change ticket/project/time records as a side effect of
    advisory generation.
-   MUST NOT "auto-create" reports on page load or shortcode render.
-   MUST NOT overwrite an existing report version.
-   MUST NOT update an advisory signal after insert.
-   MUST NOT inject "helpful defaults" into operational settings based
    on advisory findings.
-   MUST NOT show advisory data to users outside permission scope.
-   MUST NOT treat advisory outputs as the source of truth (they are
    derived artifacts).

------------------------------------------------------------------------

## 5) Stress-Test Scenarios (integration-level)

1.  **Feature flag off**
    -   Given pet_advisory_enabled=false
    -   When viewing advisory pages
    -   Then no data is shown; no background work occurs
2.  **Manual generation explicit**
    -   Given manual generation enabled
    -   When manager clicks generate QBR snapshot
    -   Then exactly one report version is created for that request and
        returned
3.  **Regeneration creates new version**
    -   Given report type+period exists with version=1
    -   When regenerate same type+period
    -   Then version=2 inserted; version=1 unchanged
4.  **No operational mutation**
    -   Given generation runs
    -   When it completes
    -   Then no changes exist in ticket/project/time tables (except
        allowed read models if any; but no domain state changes)
5.  **Permission gating**
    -   Given an agent without advisory scope
    -   When calling advisory endpoints
    -   Then 403 or filtered empty

------------------------------------------------------------------------

## Acceptance Gate

Implementation must not start until: - This contract is approved -
Versioning behaviour and "no operational mutation" is integration-tested
