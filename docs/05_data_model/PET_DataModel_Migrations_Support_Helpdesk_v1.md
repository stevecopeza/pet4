# PET Data Model & Migrations --- Support Helpdesk v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Assumptions

Ticket tables already exist. This doc defines required indexes and any
missing fields for helpdesk surfaces.

## Required Fields (if not present)

-   assigned_team_id (uuid null)
-   assigned_employee_id (uuid null)
-   resolved_at (datetime null)
-   priority (enum/int)
-   status (enum)
-   customer_id (uuid)

## Required Invariant (enforced in Domain)

-   exactly one of assigned_team_id OR assigned_employee_id is set (or
    both null if unassigned allowed)
-   for demo-critical path: require at least one assignee (config)

## Indexes (migration additions)

-   (status, created_at)
-   (resolved_at)
-   (assigned_team_id, status)
-   (assigned_employee_id, status)
-   (customer_id, status)

If SLA clock state exists: - sla_clock_state: indexes on (warning_at),
(breach_at), (ticket_id unique)

## Acceptance Criteria

-   Overview queries are covered by indexes
-   No table rewrites that threaten upgrades
