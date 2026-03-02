# PET Data Model & Migrations --- People Resilience v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Table: pet_capability_requirements

-   id (uuid pk)
-   subject_type (varchar) \# TEAM\|DEPARTMENT\|ROLE
-   subject_id (uuid/varchar)
-   requirement_type (varchar) \# SKILL\|CERT
-   requirement_id (uuid/varchar)
-   minimum_people (int)
-   importance (varchar) \# LOW\|MEDIUM\|HIGH\|CRITICAL
-   is_enabled (tinyint)
-   created_at (datetime)

Unique: - (subject_type, subject_id, requirement_type, requirement_id)

Indexes: - (subject_type, subject_id) - (importance) - (is_enabled)

## Analysis Run Tracking (optional but recommended)

Table: pet_resilience_analysis_runs - id (uuid pk) - scope (varchar) -
subject_id (varchar null) - executed_at (datetime) -
executed_by_actor_id (varchar/uuid) - results_json (longtext)

## Acceptance Criteria

-   Requirements are unique per subject
-   Analysis runs are append-only and auditable
