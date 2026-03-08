# PET API Contract --- Escalation & Risk v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Base

Namespace: /pet/v1 All endpoints require authentication. All responses
are JSON. Pagination uses: page, page_size, next_cursor (if applicable).

## Permissions (roles)

-   agent: view escalations relevant to their team or assigned work
-   manager: view + acknowledge + resolve escalations for their org
    scope
-   admin: manage escalation rules + settings

------------------------------------------------------------------------

## Endpoints

### GET /escalations

Query params: - status: OPEN\|ACKED\|RESOLVED (default OPEN) - severity:
LOW\|MEDIUM\|HIGH\|CRITICAL (optional) - source_type:
TICKET\|PROJECT\|CUSTOMER\|ADVISORY_SIGNAL (optional) - source_id: uuid
(optional) - team_id: uuid (optional) - assigned_to_employee_id: uuid
(optional) - created_after, created_before (optional ISO8601) - page,
page_size

Response: - items: \[EscalationSummary\] - page, page_size, total

EscalationSummary: - id, severity, status - source_type, source_id -
rule_id (nullable) - opened_at, acked_at, resolved_at - target_type,
target_id (derived) - headline (string) - links: {"detail": "..."}

### GET /escalations/{id}

Response: - escalation: EscalationDetail - transitions: \[Transition\]

Transition: - from_status, to_status, actor_id, occurred_at, note
(optional)

### POST /escalations/{id}/ack

Body: - note (optional string)

Response: - escalation (updated snapshot) Errors: - 409 if not in OPEN
state - 403 if not permitted

### POST /escalations/{id}/resolve

Body: - note (optional string)

Response: - escalation (updated snapshot) Errors: - 409 if not in
OPEN/ACKED state - 403 if not permitted

------------------------------------------------------------------------

## Escalation Rules

### GET /escalation-rules

Response: - items: \[RuleSummary\]

### POST /escalation-rules

Body: - name - trigger_type - criteria_json - severity - target_type -
target_id - cooldown_minutes (optional) - is_enabled (optional; default
true)

Response: - rule

### PATCH /escalation-rules/{id}

Body (partial): - name, is_enabled, criteria_json, severity,
target_type, target_id, cooldown_minutes

Response: - rule

Errors: - 400 validation - 403 permission

------------------------------------------------------------------------

## Error Model (standard)

-   code: string
-   message: string
-   details: object (optional)
