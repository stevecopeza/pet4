# PET Support Helpdesk --- Full Implementation Spec v1.0

Date: 2026-02-26 Target location: (relocated) Related existing docs:
docs/24_support_helpdesk/\*, SLA automation docs

## Goal

Deliver a working helpdesk surface that: - Shows real ticket data
(created/resolved/breached) - Surfaces SLA timers and escalations -
Supports assignment semantics (team or individual) and queue views - Is
demo-ready and production-safe

## Required Functional Areas

### 1) Overview Data Sets

-   Recent Created
-   Recent Resolved
-   SLA Warning / Breached
-   Escalated (if escalation enabled)

### 2) Assignment (single conceptual "Assignee")

Supports: - Team assignment (unclaimed) - Employee assignment (claimed)

Invariant: - Exactly one of assigned_team_id OR assigned_employee_id is
set.

### 3) SLA Timers

-   Show response/resolution due where applicable
-   Show breached duration clearly

### 4) Filters

-   status, priority, customer, team, employee, SLA state, escalation
    state

## Application Layer

Services: - HelpdeskOverviewQueryService - TicketAssignmentService
(assign team, assign employee, pull)

## Infrastructure

-   Indexes for overview queries
-   Join to sla_clock_state for SLA status/timers

## Settings / Configuration

-   pet_helpdesk_enabled (master)
-   pet_helpdesk_show_wallboard (default false)
-   pet_helpdesk_require_assignee_on_create (default true)

## API Contract (separate doc required)

-   GET /helpdesk/overview
-   POST /tickets/{id}/assign/team
-   POST /tickets/{id}/assign/employee
-   POST /tickets/{id}/pull

## UI Contract

Shortcodes: - \[pet_helpdesk_overview\] (populate real data; remove
hardcoded arrays) - \[pet_helpdesk_my_work\] -
\[pet_helpdesk_wallboard\] (read-only, auto-refresh)

Admin: - Helpdesk dashboard page - Settings

## Tests

-   Overview queries return correct results
-   Assignment invariants enforced
-   Permissions enforced

## Acceptance Criteria

-   Existing helpdesk shortcode shows real data
-   SLA + escalation indicators visible
-   Assignment works end-to-end
