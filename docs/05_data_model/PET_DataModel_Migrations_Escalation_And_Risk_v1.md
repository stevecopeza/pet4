# PET Data Model & Migrations --- Escalation & Risk v1.0

Date: 2026-02-26

## Tables

### pet_escalation_rules

-   id (uuid pk)
-   name (varchar)
-   is_enabled (tinyint)
-   trigger_type (varchar)
-   criteria_json (longtext)
-   severity (varchar)
-   target_type (varchar)
-   target_id (varchar/uuid)
-   cooldown_minutes (int null)
-   created_at (datetime)

Indexes: - (is_enabled) - (trigger_type) - (severity)

### pet_escalations

-   id (uuid pk)
-   rule_id (uuid null)
-   source_type (varchar)
-   source_id (uuid)
-   severity (varchar)
-   status (varchar)
-   open_dedupe_key (char(64) null/required for OPEN)
-   opened_at (datetime)
-   acked_at (datetime null)
-   resolved_at (datetime null)
-   opened_by_actor_id (uuid/varchar)
-   acked_by_actor_id (uuid/varchar null)
-   resolved_by_actor_id (uuid/varchar null)

Indexes: - (status, severity) - (opened_at) - (source_type, source_id)
Unique: - (open_dedupe_key)

### pet_escalation_transitions (append-only)

-   id (uuid pk)
-   escalation_id (uuid fk)
-   from_status (varchar)
-   to_status (varchar)
-   actor_id (uuid/varchar)
-   occurred_at (datetime)
-   note (text null)

Indexes: - (escalation_id, occurred_at)

## Migration Rules

-   Forward-only
-   If table exists, migration must no-op safely
-   Add indexes in separate steps if required for older MySQL
    limitations

## Backfill

-   None required initially.
-   Escalations only created prospectively.

## Acceptance Criteria

-   Unique open_dedupe_key prevents duplicates under concurrency
-   Query indexes support list views by status/severity/time
