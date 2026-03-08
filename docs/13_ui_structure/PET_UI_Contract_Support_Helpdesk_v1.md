# PET UI Contract --- Support Helpdesk v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Shortcodes

### \[pet_helpdesk_overview\]

Sections (each paged/limited): - Recent Created - Recent Resolved - SLA
Warning - SLA Breached - Escalated (if enabled)

Each row shows: - Title, Customer, Priority, Status, Assignee
(team/employee), SLA timer

### \[pet_helpdesk_my_work\]

-   My assigned tickets
-   SLA timers
-   Quick actions: Pull/Assign-to-me (if allowed)

### \[pet_helpdesk_wallboard\] (optional flag)

-   Department queue view
-   Read-only, auto-refresh
-   Emphasize breaches/escalations

## Admin Pages

-   Helpdesk Dashboard (same as overview + filters)
-   Settings

## Behaviour Rules

-   UI triggers commands for assignment; no business logic in components
-   Assignee dropdown supports both teams and employees

## Acceptance Criteria

-   No hardcoded empty arrays
-   SLA timers render correctly and update
