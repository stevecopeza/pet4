# PET API Contract --- Support Helpdesk v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Base

Namespace: /pet/v1 All endpoints require authentication. Helpdesk
endpoints require pet_helpdesk_enabled=true.

## Permissions

-   agent: view tickets in scope, self-assign, pull from team
-   manager: view department queue, assign others
-   admin: settings

------------------------------------------------------------------------

## Endpoints

### GET /helpdesk/overview

Query params (optional): - team_id, employee_id, customer_id -
window_days (default 7) - limit (default 20)

Response: - recent_created: \[TicketSummary\] - recent_resolved:
\[TicketSummary\] - sla_warning: \[TicketSummary\] - sla_breached:
\[TicketSummary\] - escalated: \[EscalationSummary\] (if escalation
enabled)

TicketSummary: - id, title, customer_name, priority, status -
assigned_team_id (nullable) - assigned_employee_id (nullable) -
created_at, resolved_at (nullable) - sla_warning_at (nullable) -
sla_breach_at (nullable) - sla_state: ACTIVE\|WARNING\|BREACHED\|NONE -
sla_time_remaining_seconds (nullable; negative if breached) - links:
{"detail": "..."}

### POST /tickets/{id}/assign/team

Body: - team_id

Response: - ticket assignment snapshot Errors: - 403 permission - 409
invalid state

### POST /tickets/{id}/assign/employee

Body: - employee_id

Response: - ticket assignment snapshot

### POST /tickets/{id}/pull

Body: - employee_id (optional; defaults to current actor's employee
identity)

Response: - ticket assignment snapshot Errors: - 409 if ticket not
team-assigned or not pullable

------------------------------------------------------------------------

## Error Model

-   code, message, details (optional)
