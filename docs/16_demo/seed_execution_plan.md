# PET Demo Seed Execution Plan v1.2

Version: 1.2\
Date: 2026-03-23\
Status: Binding (Seed Pipeline + Determinism)

## Purpose

Define the deterministic, ordered demo seed pipeline and its failure
behavior.

## Core Rules

-   Seed uses **application commands/handlers** (preferred) or domain
    mutation APIs; no direct property setting.
-   Every created entity is recorded in the Demo Seed Ledger with
    `seed_run_id`.
-   Every transition must be preceded by readiness validation (see
    Readiness Gates).
-   On transition failure, seed must **degrade** (leave entity in
    closest legal earlier state) unless the artifact is demo-critical
    for PASS.

## Pipeline Overview (Steps)

0.  `seed.featureFlags` --- seed all feature flags (enabled) into pet_settings
1.  `seed.meta` --- create seed run id + seed ledger context
2.  `seed.employees` --- 8 employees with realistic roles
3.  `seed.customers` --- 4 customers + 6 sites + 7 contacts
4.  `seed.org` --- teams + memberships
5.  `seed.calendar` --- default calendar + holidays
6.  `seed.capability` --- roles, skills, certifications, KPIs
7.  `seed.leave` --- leave requests (approved/pending/rejected)
8.  `seed.catalog` --- 14 catalog items
9.  `seed.commercial` --- 7 quotes (draft/accepted mix)
10. `seed.delivery` --- 3 projects from accepted quotes
11. `seed.support` --- 13 support tickets with priority/status variety, SLA clocks, assignment mix (employee/queue/unassigned)
11b. `seed.backboneTickets` --- 6 project tickets (WBS parent + 5 children) + 4 internal tickets + 3 ticket_links, assigned to employees/queues
12. `seed.workOrchestration` --- work items + department queues for all open tickets (department derived from primary_container: support/delivery/operations)
13. `seed.time` --- ~30 time entries distributed across 8+ tickets, varied employees, 2 correction entries (reversal + re-log with duration_minutes)
14. `seed.knowledge` --- 6 articles
15. `seed.feed` --- 47 feed events + 2 announcements
16. `seed.conversations` --- 18 ticket-context conversations
17. `seed.projectTasks` --- 27 tasks across 3 projects
18. `seed.billing` --- exports, QB invoices/payments, integration runs
19. `seed.eventBackbone` --- domain event expectations
20. `seed.summary` --- return counts + anchors + step results
21. `seed.opsVerify` --- operational verification signals:
    - health endpoint exposes `readiness_status` and `readiness_reasons`
    - diagnostics exposes `registry_summary.active_runs_count`
    - active run counts align between health and diagnostics

Note: `seed.featureFlags` MUST run before any other step. Feature flags
gate controller registration (e.g. TicketController requires
`pet_helpdesk_enabled`). Without flags, seeded entities may not be
accessible via API.

## Failure Modes

### Domain failure during acceptance/submission/etc.

-   Record a step issue:
    -   `error=domain_exception`
    -   include message, entity key (Q1/Q4), and gate that failed
-   Degrade:
    -   keep quote Draft/Ready (do not accept)
    -   keep time Draft (do not submit)
-   If degraded artifact is required for PASS (see Success Criteria),
    mark `overall=FAIL` and return 422 **only if** the dataset cannot be
    considered demoable.

### Schema capability missing

-   If required tables/columns missing:
    -   Preflight should have failed; seed may proceed in PARTIAL mode
        if allowed.
    -   Step is marked `SKIPPED_CAPABILITY` and overall becomes PARTIAL.

## Deterministic Content Rules

-   All names must use `DEMO ...` prefixes.
-   Amounts/rates must be fixed constants.
-   IDs are system-generated but anchor mapping must be returned as
    stable keys.

## Mermaid: Pipeline

``` mermaid
flowchart TD
  FF[seed.featureFlags] --> A[seed.employees]
  A --> B[seed.customers]
  B --> C[seed.org + calendar + capability + leave]
  C --> D[seed.catalog]
  D --> E[seed.commercial]
  E --> F[seed.delivery]
  F --> G[seed.support]
  G --> G2[seed.backboneTickets]
  G2 --> H[seed.workOrchestration]
  H --> I[seed.time]
  I --> J[seed.knowledge + feed]
  J --> K[seed.conversations + projectTasks]
  K --> L[seed.billing + eventBackbone]
  L --> M[seed.summary]
```
