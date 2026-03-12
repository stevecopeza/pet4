# PET Demo Seed Ledger and Purge Policy v1.1

Version: 1.1\
Date: 2026-02-14\
Status: Binding (Safety + Governance)

## Purpose

Ensure purge is surgical, safe, and never affects non-demo data.

## Ledger Table

**Name:** `wp_pet_demo_seed_ledger` (prefix applies)\
**Ownership:** Infrastructure (custom table)

### Columns

-   `id` BIGINT PK AUTO_INCREMENT
-   `seed_run_id` CHAR(36) NOT NULL
-   `entity_type` VARCHAR(64) NOT NULL (e.g., customer, quote, project,
    ticket, time_entry, event)
-   `entity_id` VARCHAR(64) NOT NULL
-   `entity_key` VARCHAR(64) NULL (e.g., Q1, P1, T1)
-   `created_at` DATETIME NOT NULL
-   `last_seen_at` DATETIME NOT NULL (updated on seed re-run
    reconciliation if enabled)
-   `purge_status` VARCHAR(32) NOT NULL DEFAULT 'ACTIVE'
    (ACTIVE|PURGED|SKIPPED|ARCHIVED)
-   `purged_at` DATETIME NULL
-   `skip_reason` VARCHAR(128) NULL
-   `user_touched` TINYINT(1) NOT NULL DEFAULT 0
-   `fingerprint` CHAR(64) NULL (optional stable hash of critical fields
    for touch detection)

### Indexes

-   UNIQUE `(seed_run_id, entity_type, entity_id)`
-   INDEX `(entity_type, entity_id)`
-   INDEX `(seed_run_id, purge_status)`

## User-Touched Detection

Goal: never delete demo items the user interacted with.

### Policy (Recommended)

An entity is considered `user_touched=1` if any of the following are
true: - It has a `updated_at` (or equivalent) later than the seed run
`created_at` and the update was not performed by the seed service
user/context. - Its fingerprint differs from the recorded fingerprint
(if fingerprinting implemented). - It is referenced by any non-demo
entity (foreign key relationship) created outside the seed ledger.

If touch detection is not fully implementable per entity type, default
to **SKIP**, not delete.

## Purge Modes

### Mode 1: Safe Purge (Default)

-   Purge only entities in ledger for the given `seed_run_id` where
    `user_touched=0`.
-   For entities that cannot be safely deleted due to references,
    **archive** (if supported) or skip.

### Mode 2: Force Purge (Admin-only)

-   Still must not delete non-demo entities.
-   May purge even if user_touched=1 only if explicitly confirmed in UI
    (out of scope for current seed API contract).

## Purge Ordering

Purge must respect dependencies to avoid FK/constraint failures: 1.
Derived/projection rows (feeds, advisory signals) 2. Time entries (if
deletable; otherwise archive) 3. Tickets 4. Projects 5. Quotes (draft
only if deletable; accepted may be immutable---prefer archive marker) 6.
Contacts/Sites/Customers (only if no user-owned references)

## Purge Output

Must report: - purged/archived/skipped counts - skipped items with
reason - whether overall PASS was achieved
