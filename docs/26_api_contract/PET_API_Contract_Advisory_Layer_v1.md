# PET API Contract --- Advisory Layer v1.0

Date: 2026-02-26

## Base

Namespace: /pet/v1 All endpoints require authentication. Advisory
endpoints require pet_advisory_enabled=true.

## Permissions

-   manager: view signals, generate reports, view reports
-   exec_readonly: view reports
-   admin: settings

------------------------------------------------------------------------

## Endpoints

### GET /advisory/signals

Query params: - type, severity - subject_type, subject_id -
detected_after, detected_before - page, page_size

Response: - items: \[Signal\]

Signal: - id, type, severity - subject_type, subject_id - detected_at -
facts_json (optional summary) - evidence_refs (optional)

### GET /advisory/reports

Query params: - report_type (QBR_SNAPSHOT\|MATURITY_SNAPSHOT) -
period_end_after, period_end_before - page, page_size

Response: - items: \[ReportSummary\]

### GET /advisory/reports/{id}

Response: - report (full render model)

### POST /advisory/reports/qbr-snapshot

Body: - period_start (ISO date) - period_end (ISO date) -
include_sections (optional list)

Response: - report summary + id

### POST /advisory/reports/maturity-snapshot

Same shape as above.

------------------------------------------------------------------------

## Error Model

-   code, message, details
