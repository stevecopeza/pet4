# PET API Contract --- People Resilience v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Base

Namespace: /pet/v1 All endpoints require authentication. Resilience
endpoints require pet_people_resilience_enabled=true.

## Permissions

-   manager: view summaries, run analysis
-   admin: manage requirements + settings

------------------------------------------------------------------------

## Endpoints

### GET /resilience/summary

Query params: - team_id (optional) - window_days (optional)

Response: - teams: \[TeamSummary\]

TeamSummary: - team_id, team_name - coverage_percent - spof_count -
critical_spof_count - last_analyzed_at

### GET /resilience/spof

Query params: - severity (optional) - team_id (optional) -
skill_id/cert_id (optional)

Response: - items: \[SpofItem\]

SpofItem: - requirement_id - subject_type, subject_id - requirement
(skill/cert, minimum_people, importance) - qualifying_people_count -
severity - evidence (people ids/names as permitted)

### POST /resilience/requirements

Body: - subject_type, subject_id - requirement_type (SKILL\|CERT) -
requirement_id - minimum_people - importance

Response: - requirement

### PATCH /resilience/requirements/{id}

Body: partial update

### POST /resilience/analyze

Body: - scope: ALL\|TEAM - team_id (optional)

Response: - run_id - analyzed_at - signal_counts

------------------------------------------------------------------------

## Error Model

-   code, message, details
