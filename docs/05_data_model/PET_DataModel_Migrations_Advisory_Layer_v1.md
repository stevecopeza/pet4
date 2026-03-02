# PET Data Model & Migrations --- Advisory Layer v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Tables

### pet_advisory_signals (immutable)

-   id (uuid pk)
-   type (varchar)
-   severity (varchar)
-   subject_type (varchar)
-   subject_id (uuid/varchar)
-   detected_at (datetime)
-   facts_json (longtext)
-   evidence_refs_json (longtext null)

Indexes: - (type, severity) - (subject_type, subject_id) - (detected_at)

### pet_advisory_reports (immutable, versioned)

-   id (uuid pk)
-   report_type (varchar)
-   period_start (date)
-   period_end (date)
-   generated_at (datetime)
-   generated_by_actor_id (varchar/uuid)
-   version (int)
-   content_json (longtext)
-   status (varchar optional: GENERATED\|PUBLISHED)

Unique: - (report_type, period_start, period_end, version)

Indexes: - (report_type, generated_at) - (period_end)

## Migration Rules

-   Forward-only, tolerant if tables exist
-   Add indexes safely

## Acceptance Criteria

-   Reports are immutable (no updates; only inserts)
-   Version increments per (type,period) when regenerating
