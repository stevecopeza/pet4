# PET Lifecycle Integration Contract --- Support Helpdesk v1.0

Date: 2026-02-26

## Purpose

Define helpdesk surfaces and behaviours in the lifecycle of Tickets,
including explicit render/creation/mutation rules, prohibited
behaviours, and stress-test scenarios.

This document is REQUIRED prior to implementation.

------------------------------------------------------------------------

## Parent Entity

-   Ticket is the source of truth. Helpdesk is a *read surface* plus
    limited command surface for assignment (and only assignment) where
    permitted.

------------------------------------------------------------------------

## 1) Render Rules

### Helpdesk UI MUST render when

-   pet_helpdesk_enabled == true
-   viewer authenticated
-   viewer has ticket visibility scope

### Helpdesk UI MUST NOT render when

-   pet_helpdesk_enabled == false
-   viewer unauthenticated
-   schema prerequisites missing → fail fast with clear admin error, not
    fatal

### Wallboard MUST render only when

-   pet_helpdesk_show_wallboard == true
-   view is read-only, auto-refresh permitted

------------------------------------------------------------------------

## 2) Creation Rules

Helpdesk must NOT create any operational records merely by rendering.

Allowed creations related to helpdesk: - None, except standard Ticket
creation flows already defined elsewhere (if ticket creation UI is part
of helpdesk; otherwise out of scope).

------------------------------------------------------------------------

## 3) Mutation Rules

Helpdesk is allowed to issue *only these* ticket mutations via
commands: - assign to team - assign to employee - pull from team

Mutation constraints: - Must respect assignment invariant: exactly one
of team/employee is set (or both null only if explicitly allowed by
config; default requires assignee) - Must record assignment events and
appear in feed - Must be permission-gated: - agents: self-assign/pull -
managers: assign others

All other ticket mutations are prohibited via helpdesk surfaces (status
changes, edits, deletions).

------------------------------------------------------------------------

## 4) Prohibited Behaviours (must NOT happen)

-   MUST NOT render hardcoded arrays (no fake data in production mode).
-   MUST NOT mutate ticket status, priority, SLA policy, or customer via
    helpdesk overview.
-   MUST NOT auto-assign tickets on creation unless explicitly
    configured and documented.
-   MUST NOT allow dual assignment (team and employee simultaneously).
-   MUST NOT allow pulling a ticket that is already employee-assigned.
-   MUST NOT create SLA clock state from helpdesk rendering.
-   MUST NOT infer defaults (team/assignee) beyond explicit rules in
    config.
-   MUST NOT bypass domain permission checks from UI components.

------------------------------------------------------------------------

## 5) Stress-Test Scenarios (integration-level)

1.  **Feature flag off**
    -   Given pet_helpdesk_enabled=false
    -   When shortcode renders
    -   Then it does not expose data and does not error fatally
2.  **Overview uses real data**
    -   Given tickets exist
    -   When overview loads
    -   Then lists are populated from repositories/services, not empty
        hardcoded arrays
3.  **Assignment invariant**
    -   Given a ticket assigned to team
    -   When agent pulls it
    -   Then team assignment is removed and employee assignment set
        atomically
4.  **Illegal pull**
    -   Given a ticket already assigned to another employee
    -   When agent attempts pull
    -   Then 409, no change
5.  **Manager reassignment**
    -   Given manager scope
    -   When manager assigns ticket to employee
    -   Then assignment event dispatched and feed updated
6.  **SLA timers read-only**
    -   Given SLA clock exists
    -   When viewing overview
    -   Then SLA state is displayed but no SLA state mutation occurs

------------------------------------------------------------------------

## Acceptance Gate

Implementation must not start until: - This contract is approved -
Assignment command permissions and invariant behaviour are explicitly
test-covered
