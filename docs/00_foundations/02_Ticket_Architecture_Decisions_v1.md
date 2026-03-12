STATUS: AUTHORITATIVE — BINDING DECISIONS
SCOPE: Ticket Backbone Architecture
VERSION: v1
DATE: 2026-03-06

# Ticket Architecture Decisions (v1)

This document records the binding architectural decisions for the PET ticket model. These decisions resolve contradictions that existed across earlier documentation and establish the single authoritative direction for implementation.

All other documents in the PET docs set must conform to these decisions. Where a previous document contradicts a decision recorded here, this document takes precedence.

## Decision 1: Tickets are created at acceptance only

Quote draft tasks are managed by the quote builder. No tickets exist in `wp_pet_tickets` until a quote is accepted.

Rationale:
- Draft quote lines are not commitments — they are negotiation artifacts.
- Creating draft tickets adds noise to the ticket system.
- Quote revisions become much cleaner without ticket cleanup on deleted/edited draft lines.
- The quote builder already manages its own task records (`wp_pet_quote_tasks`).

This revokes the previous principle (01_Ticket_Backbone_Principles_and_Invariants_v1.md, lines 35–36) that stated "Quote labour items create draft tickets immediately."

## Decision 2: One ticket per sold item, immutable sold fields

Each accepted quote task produces exactly one ticket. That ticket carries `sold_minutes` and `sold_value_cents` as immutable baseline fields, locked at the moment of acceptance.

There is no separate "baseline ticket" record. The accepted quote snapshot (already immutable) serves as the external audit record of "what was sold." The ticket's `sold_minutes` is the operational baseline.

## Decision 3: No baseline clone ticket

The Option B model (baseline ticket + execution ticket clone) is rejected.

Rationale:
- Duplicates the domain object for no functional gain.
- Introduces cross-referencing complexity (baseline_ticket_id on every execution ticket).
- The immutable quote snapshot already preserves the commercial record.

This revokes the previous principle (01_Ticket_Backbone_Principles_and_Invariants_v1.md, line 78) that described "Ticket (draft) → Ticket (baseline) + Ticket (execution)" as the target state.

## Decision 4: Change orders create new tickets linked by `change_order_source_ticket_id`

When scope changes after acceptance, a new ticket is created with its own `sold_minutes`. The new ticket references the original via a dedicated `change_order_source_ticket_id` field.

The change order ticket is NOT a child of the original (no parent_ticket_id relationship). This avoids:
- Turning the original sold ticket into a rollup.
- Changing the original ticket's leaf/time-logging behaviour.
- Distorting WBS structure.

Reporting aggregates original + change order tickets by following the explicit link.

## Decision 5: No stored `planned_minutes`

`planned_minutes` (the sum of children's `estimated_minutes`) is not stored on the parent ticket.

It is derived at query time: `SELECT SUM(estimated_minutes) FROM wp_pet_tickets WHERE parent_ticket_id = ?`

Rationale:
- Storing it creates cache invalidation complexity.
- Every child `estimated_minutes` change would need to propagate to the parent.
- No mechanism exists for this today, and adding one introduces stale-state bugs.
- PET's data model favours derived truth over cached copies.

If performance becomes a problem in the project view, add a materialised projection — not a field on the ticket entity.

## Decision 6: `baseline_locked` is not a status — use a boolean field

A ticket can be both "in progress" (operational status) and "baseline locked" (commercial property) simultaneously. These are orthogonal concerns.

Remove `baseline_locked` from the project lifecycle status set.

Add `is_baseline_locked` TINYINT(1) NOT NULL DEFAULT 0 to the ticket schema. This field is set to 1 when the ticket is created from an accepted quote (i.e., `sold_minutes IS NOT NULL` and created via acceptance).

The project lifecycle statuses remain clean:
- `planned` → `ready` → `in_progress` → `blocked` → `done` → `closed`

## Decision 7: Time is logged against leaf tickets only

Precise rule:

> Time is logged against leaf tickets only. A ticket with no children is a leaf and may receive time directly. Once split into children, it becomes a rollup and no longer accepts direct time.

An unsplit sold ticket is a leaf and accepts time directly. The `canAcceptTimeEntries()` domain method returns `!$this->isRollup`, which correctly implements this rule.

Roll-up progress, time, and cost are computed by aggregating from leaves.

## Decision 8: Payment schedules reference the sold ticket directly

Payment schedule items reference the ticket created at acceptance — not a "baseline ticket" (which no longer exists as a concept).

Fields:
- `ticket_id` — the sold ticket
- `quote_line_id` / `quote_version_id` — for commercial traceability back to the quote

No schedule item may reference mutable execution-only artifacts without a sold anchor.

## Decision 9: WBS depth is unlimited; `root_ticket_id` points to the sold root

Execution children can be split further (grandchildren, etc.). The hierarchy uses:

- `parent_ticket_id` — immediate structural parent in the WBS tree.
- `root_ticket_id` — always points to the top-level sold commitment ticket for the entire chain.

For any depth of WBS decomposition:
- `parent_ticket_id` gives the local hierarchy.
- `root_ticket_id` gives the original sold anchor.

This makes variance reporting straightforward: `sold_minutes` on the root minus `SUM(estimated_minutes)` on all leaves under that root.

When a change order ticket (linked via `change_order_source_ticket_id`) is split into children, `root_ticket_id` on those children points to the change order ticket — because the change order is its own sold commitment. Reporting aggregates across the original + change orders by following `change_order_source_ticket_id`.

## Decision 10: Remove `ticket_mode`

The `ticket_mode` field (which carried values like `'execution'`, `'baseline'`, `'draft_quote'`) is no longer meaningful under the single-ticket model.

- There is no baseline record to distinguish from an execution record.
- There are no draft quote tickets.
- "Execution" is not a mode — it is the normal state of a ticket.

`ticket_mode` should be removed from the schema. Existing rows carrying `ticket_mode = 'execution'` should have the column dropped in a future migration.

If any code currently switches on `ticket_mode`, replace with:
- `lifecycle_owner` for context-specific behaviour.
- `ticket_kind` for classification (work, incident, change_order, etc.).
- `is_baseline_locked` for commercial lock state.

---

## Summary of revoked prior statements

These specific statements from earlier documents are revoked by the decisions above:

- `01_Ticket_Backbone_Principles_and_Invariants_v1.md` line 35–36: "Quote labour items create draft tickets immediately. Draft quote tickets are mutable while the quote is editable." → Revoked by Decision 1.
- `01_Ticket_Backbone_Principles_and_Invariants_v1.md` line 78: "Quote labour → Ticket (draft) → Ticket (baseline) + Ticket (execution) mapping is explicit." → Revoked by Decisions 2 and 3.
- `04_Quote_to_Ticket_to_Project_Flow_v1.md` lines 31–41: Draft ticket creation during quoting. → Revoked by Decision 1.
- `04_Quote_to_Ticket_to_Project_Flow_v1.md` lines 53–68: Baseline vs execution ticket model. → Revoked by Decision 3.
- `03_Ticket_Lifecycle_and_State_Machines_v1.md` line 28: `baseline_locked` as a project status. → Revoked by Decision 6.
