# PET Data Model & Migrations --- Escalation & Risk v1.0

Date: 2026-02-26
Updated: 2026-03-12 (aligned with Phase 1 implementation)

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

### pet_escalations (as implemented)

-   id (bigint unsigned, auto-increment pk)
-   escalation_id (char(36), unique)
-   source_entity_type (varchar(50))
-   source_entity_id (bigint unsigned)
-   severity (varchar(20), default 'MEDIUM')
-   status (varchar(20), default 'OPEN')
-   reason (text)
-   metadata_json (longtext, default '{}')
-   open_dedupe_key (varchar(64), nullable, unique)
    -   Computed: `SHA-256(source_entity_type|source_entity_id|severity|reason)`
    -   Set for OPEN/ACKED rows; cleared to NULL on RESOLVED
-   created_by (bigint unsigned, nullable)
-   acknowledged_by (bigint unsigned, nullable)
-   resolved_by (bigint unsigned, nullable)
-   created_at (datetime)
-   acknowledged_at (datetime, nullable)
-   resolved_at (datetime, nullable)

Indexes: - escalation_id (unique) - (source_entity_type, source_entity_id) - (status) - (severity) - (created_at)
Unique: - (open_dedupe_key)

Note: `rule_id` is deferred to Phase 2 (rules engine).

### pet_escalation_transitions (append-only)

-   id (bigint unsigned, auto-increment pk)
-   escalation_id (bigint unsigned)
-   from_status (varchar(20), **nullable** — NULL for initial NULL→OPEN transition)
-   to_status (varchar(20))
-   transitioned_by (bigint unsigned, nullable)
-   reason (text, nullable)
-   transitioned_at (datetime)

Indexes: - (escalation_id) - (transitioned_at)

## Migrations

### CreateEscalationTables

Creates `pet_escalations` and `pet_escalation_transitions` with the
base schema (before dedupe key support).

### AddEscalationDedupeKey

Adds `open_dedupe_key` column + UNIQUE index to `pet_escalations`.
Modifies `pet_escalation_transitions.from_status` to allow NULL.
Adds `reason` column to `pet_escalation_transitions`.

## Migration Rules

-   Forward-only
-   If table/column exists, migration must no-op safely (guarded by
    DESCRIBE check)
-   Add indexes in separate steps if required for older MySQL
    limitations

## Backfill

-   None required initially.
-   Escalations only created prospectively.

## Acceptance Criteria

-   Unique open_dedupe_key prevents duplicates under concurrency
-   Duplicate-key insert failures detected and surfaced as
    `DuplicateKeyException` (not silently swallowed)
-   Query indexes support list views by status/severity/time
-   NULL from_status supported for initial transition
