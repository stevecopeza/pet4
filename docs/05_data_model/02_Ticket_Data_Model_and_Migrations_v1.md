STATUS: IMPLEMENTED
SCOPE: Ticket Backbone Schema Extension
VERSION: v2
SUPERSEDES: v1.1
DATE: 2026-03-06

# Ticket Backbone — Data Model and Migrations (v2)

> Governed by `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.

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
- `quote_id` BIGINT UNSIGNED NULL     (links ticket to the accepted quote)
- `quote_version` INT NULL            (optional: snapshot version)
- `phase_id` BIGINT UNSIGNED NULL     (quote/project phase grouping)
- `parent_ticket_id` BIGINT UNSIGNED NULL  (WBS tree — immediate structural parent)
- `root_ticket_id` BIGINT UNSIGNED NULL    (always points to the top-level sold commitment ticket for the WBS chain; denormalized for reporting)
- `change_order_source_ticket_id` BIGINT UNSIGNED NULL  (links a change order ticket to the original sold ticket it amends; NOT a parent/child relationship)

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
- `sold_minutes` INT NULL (baseline sold effort for labour; immutable once set at acceptance)
- `sold_value_cents` BIGINT NULL (baseline sold value; immutable once set at acceptance)
- `estimated_minutes` INT NULL (execution estimate; may differ from sold_minutes; mutable)
- `is_baseline_locked` TINYINT(1) NOT NULL DEFAULT 0  
  Set to 1 when ticket is created from an accepted quote. When 1, `sold_minutes` and `sold_value_cents` are immutable. This is an orthogonal property, NOT a lifecycle status.

Note: `planned_minutes` (sum of children's `estimated_minutes`) is NOT stored. It is derived at query time: `SELECT SUM(estimated_minutes) FROM wp_pet_tickets WHERE parent_ticket_id = ?`

Note: `remaining_minutes` is NOT stored. It is derived from actual time logged vs estimated.

**Leaf-only enforcement**
- `is_rollup` TINYINT(1) NOT NULL DEFAULT 0  
  Rule: if a ticket has children, set is_rollup=1; time logging must enforce leaf-only. An unsplit sold ticket is a leaf.

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

### 5) Quotes: ticket linkage fields

Additive:
- In `wp_pet_quote_tasks` add `ticket_id` BIGINT NULL (set at quote acceptance, NOT during quoting).
- In `wp_pet_quote_milestones` add `phase_id` BIGINT NULL (if phases become first-class).

Quote tasks reference tickets only after acceptance. No tickets exist during the draft/sent phase.

### 6) Deprecated / removed fields

- `ticket_mode` — to be dropped. Was used for values like 'execution', 'baseline', 'draft_quote'. No longer meaningful under the single-ticket model. Replace with `lifecycle_owner` (context), `ticket_kind` (classification), and `is_baseline_locked` (commercial lock).

## Migration sequencing (forward-only)

M1. ✅ Add ticket extension columns to `wp_pet_tickets`. (Migration 008)
M2. ✅ Create `wp_pet_ticket_links`. (Migration 008)
M3. ✅ Verify `ticket_id` on `wp_pet_time_entries` — already present.
M4. ✅ Add `ticket_id` to `wp_pet_tasks`. (Migration 008)
M5. ✅ Backfill: seed creates tickets with full backbone fields; existing tasks bridged.
M6. Add ticket_id to quote task records (set at acceptance, not during quoting) — future.
M7. ✅ Add `change_order_source_ticket_id` to `wp_pet_tickets`. (Migration: `AddTicketSoldArchitectureColumns`)
M8. ✅ Add `is_baseline_locked` to `wp_pet_tickets`. (Migration: `AddTicketSoldArchitectureColumns`)
M9. Drop `ticket_mode` column from `wp_pet_tickets` — future (column still in use by TicketController).

All backfills must be idempotent and safe to re-run.

### Namespace decision
Ticket entity remains in `Domain\Support\Entity\Ticket`. Although tickets now serve support, project, and internal contexts, a migration to a dedicated `Domain\Ticket` namespace was deferred to avoid churn. The `lifecycle_owner` field governs context-specific behaviour within the single entity.

### `root_ticket_id` semantics
- For a top-level sold ticket: `root_ticket_id` = self.
- For WBS children/grandchildren: `root_ticket_id` = the top-level sold ticket.
- For change order tickets: `root_ticket_id` = self (a change order is its own sold commitment).
- For children of change order tickets: `root_ticket_id` = the change order ticket.
- Reporting aggregates across original + change orders by following `change_order_source_ticket_id`.

## Data safety rules

- Never delete or rewrite accepted quote snapshots or submitted time.
- Backfill must preserve existing foreign keys and IDs; only add linking columns.
- If ambiguity exists, mark records with `malleable_data` flags for manual reconciliation.
