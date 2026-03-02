STATUS: IMPLEMENTED
SCOPE: Ticket Backbone Schema Extension
VERSION: v1.1

# Ticket Backbone — Data Model and Migrations (v1)

This document defines the **required tables/fields** to make Ticket the universal work unit while maintaining backward compatibility.

## Current reality (audit snapshot)

- Support tickets: `wp_pet_tickets` (support-centric).
- Delivery tasks: `wp_pet_tasks` (project tasks).
- Time entries: `wp_pet_time_entries` has `task_id` only; **no `ticket_id`**.
- Work orchestration: `wp_pet_work_items` supports `source_type` = `ticket` and `project_task`.

This is a split spine.

## Target: Ticket as single work unit

### 1) Extend `wp_pet_tickets` to support cross-system contexts

Additive columns (names are normative; exact types may follow existing conventions):

**Identity / container**
- `primary_container` ENUM('support','project','internal') NOT NULL DEFAULT 'support'
- `project_id` BIGINT UNSIGNED NULL  (when primary_container = 'project' or linked)
- `quote_id` BIGINT UNSIGNED NULL     (draft quote tickets and baseline linkage)
- `quote_version` INT NULL            (optional: snapshot version)
- `phase_id` BIGINT UNSIGNED NULL     (quote/project phase grouping)
- `parent_ticket_id` BIGINT UNSIGNED NULL  (WBS tree)
- `root_ticket_id` BIGINT UNSIGNED NULL    (denormalized for reporting; optional)

**Classification**
- `ticket_kind` VARCHAR(50) NOT NULL DEFAULT 'work'  
  Allowed values: `work`, `incident`, `change_order`, `admin`, `internal`, `delivery` (final list per docs)
- `department_id` BIGINT UNSIGNED NULL
- `required_role_id` BIGINT UNSIGNED NULL
- `skill_level` VARCHAR(50) NULL

**Commercial context**
- `billing_context_type` ENUM('agreement','project','adhoc','internal') NOT NULL DEFAULT 'adhoc'
- `agreement_id` BIGINT UNSIGNED NULL (for entitlement/SLA contracts; separate from SLA template)
- `rate_plan_id` BIGINT UNSIGNED NULL (or equivalent)
- `is_billable_default` TINYINT(1) NOT NULL DEFAULT 1

**Sold baseline / estimation**
- `sold_minutes` INT NULL (baseline sold effort for labour)
- `sold_value_cents` BIGINT NULL (optional; depends on money approach)
- `estimated_minutes` INT NULL (execution estimate; may differ)
- `remaining_minutes` INT NULL (derived or projection; optional)

**Leaf-only enforcement**
- `is_rollup` TINYINT(1) NOT NULL DEFAULT 0  
  Rule: if a ticket has children, set is_rollup=1; time logging must enforce leaf-only.

**Lifecycle authority markers**
- `lifecycle_owner` ENUM('support','project','internal') NOT NULL DEFAULT 'support'  
  Must match primary container; used to guard cross-context edits.

> Note: Add fields only. No destructive schema changes.

### 2) Add `wp_pet_ticket_links` for cross-context references

New table: `wp_pet_ticket_links`
- `id` BIGINT PK
- `ticket_id` BIGINT NOT NULL
- `link_type` ENUM('project','quote','site','customer','ticket','external') NOT NULL
- `linked_id` VARCHAR(64) NOT NULL
- `created_at` DATETIME NOT NULL

Used for “helpdesk ticket assisting project” without changing primary container.

### 3) Add `ticket_id` to time entries (backward compatible)

Alter `wp_pet_time_entries` (additive):
- `ticket_id` BIGINT UNSIGNED NULL
- index on (`ticket_id`)

Rules:
- `ticket_id` may be NULL only while status='draft' (timers/drafts).
- At submit/lock, `ticket_id` must be NOT NULL (domain enforced).

### 4) Bridge legacy tasks to tickets

Alter `wp_pet_tasks` (additive):
- `ticket_id` BIGINT UNSIGNED NULL
- unique/index on `ticket_id`

Rules:
- For existing tasks, create corresponding tickets and backfill `tasks.ticket_id`.
- New work should create tickets first; tasks become a projection/compatibility view.

### 5) Quotes: optional ticket linkage fields

Additive:
- In `wp_pet_quote_tasks` add `ticket_id` BIGINT NULL (draft quote tickets).
- In `wp_pet_quote_milestones` add `phase_id` BIGINT NULL (if phases become first-class).

If quote components are retained, they must be able to reference tickets directly.

## Migration sequencing (forward-only)

M1. ✅ Add ticket extension columns to `wp_pet_tickets`. (Migration 008)
M2. ✅ Create `wp_pet_ticket_links`. (Migration 008)
M3. ✅ Verify `ticket_id` on `wp_pet_time_entries` — already present.
M4. ✅ Add `ticket_id` to `wp_pet_tasks`. (Migration 008)
M5. ✅ Backfill: seed creates tickets with full backbone fields; existing tasks bridged.
M6. Add ticket_id to quote task records (draft linkage) — future.

All backfills must be idempotent and safe to re-run.

### Namespace decision
Ticket entity remains in `Domain\Support\Entity\Ticket`. Although tickets now serve support, project, and internal contexts, a migration to a dedicated `Domain\Ticket` namespace was deferred to avoid churn. The `lifecycle_owner` field governs context-specific behaviour within the single entity.

## Data safety rules

- Never delete or rewrite accepted quote snapshots or submitted time.
- Backfill must preserve existing foreign keys and IDs; only add linking columns.
- If ambiguity exists, mark records with `malleable_data` flags for manual reconciliation.
