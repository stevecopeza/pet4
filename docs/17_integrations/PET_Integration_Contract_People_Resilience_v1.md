# PET Lifecycle Integration Contract --- People Resilience v1.0

Date: 2026-02-26

## Purpose

Define when resilience requirements, analyses, and SPOF indicators exist
in the lifecycle of People/Teams, including render/creation/mutation
rules, prohibited behaviours, and stress-test scenarios.

This document is REQUIRED prior to implementation.

------------------------------------------------------------------------

## Parent Entities

-   Team/Department/Role as subjects for requirements
-   People skills/certifications as factual inputs

Resilience outputs are derived artifacts; they must not mutate people
records.

------------------------------------------------------------------------

## 1) Render Rules

### Requirements Manager MUST render when

-   pet_people_resilience_enabled == true
-   viewer is admin

### Resilience Dashboard MUST render when

-   pet_people_resilience_enabled == true
-   viewer is manager/admin

### "Run Analysis" MUST render when

-   pet_people_resilience_enabled == true
-   viewer is manager/admin
-   manual execution allowed (default yes)

### Resilience MUST NOT render when

-   pet_people_resilience_enabled == false
-   schema prerequisites missing → fail fast with clear admin error, not
    fatal

------------------------------------------------------------------------

## 2) Creation Rules

### CapabilityRequirement MUST be created when

-   admin explicitly creates it via UI/API
-   uniqueness constraints prevent duplicates per subject+requirement

### Analysis Run / SPOF outputs MUST be created when

-   manager/admin explicitly runs analysis (manual)
-   (optional) scheduled analysis runs only when schedule flag enabled

### Analysis MUST NOT run when

-   feature flag disabled
-   invoked implicitly by page load/render
-   invoked on every ticket change (no high-frequency coupling)

Idempotency rule: - analysis produces signals idempotently per
run_id+requirement_id - repeated submission of same run_id must not
duplicate signals

------------------------------------------------------------------------

## 3) Mutation Rules

Requirements: - editable by admin (changes are explicit; may be
evented) - disable/enable is permitted - changes do not retroactively
mutate past analysis run records

Analysis runs: - append-only (immutable records)

Signals (if emitted via Advisory): - immutable; insert-only

------------------------------------------------------------------------

## 4) Prohibited Behaviours (must NOT happen)

-   MUST NOT modify a person's skills/certs based on analysis.
-   MUST NOT auto-create requirements (no injected defaults) except
    default values on the *requirement form* for convenience.
-   MUST NOT run analysis automatically on every page render.
-   MUST NOT open escalations for non-critical SPOFs unless explicitly
    configured.
-   MUST NOT duplicate SPOF signals for the same run.
-   MUST NOT expose individual-level evidence to users without
    permission (PII minimization by role).

------------------------------------------------------------------------

## 5) Stress-Test Scenarios (integration-level)

1.  **Feature flag off**
    -   Given pet_people_resilience_enabled=false
    -   When accessing resilience pages/endpoints
    -   Then blocked/hidden without side effects
2.  **Requirement uniqueness**
    -   Given a requirement exists for team+skill
    -   When admin attempts to create duplicate
    -   Then 409, no duplicate row
3.  **Manual analysis explicit**
    -   Given manager clicks Run Analysis
    -   Then one analysis run record created and SPOF list derived
4.  **Idempotent signals**
    -   Given an analysis run with run_id
    -   When analyzer retries for same run_id
    -   Then no duplicated advisory signals
5.  **Escalation coupling controlled**
    -   Given critical SPOF detected
    -   When pet_people_resilience_escalate_on_critical_spof=false
    -   Then no escalation is opened

------------------------------------------------------------------------

## Acceptance Gate

Implementation must not start until: - This contract is approved -
Idempotency and privacy gating are tested at integration level
