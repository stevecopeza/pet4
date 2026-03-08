STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v2
SUPERSEDES: v1
DATE: 2026-03-06

# Ticket Backbone — Principles and Invariants (v2)

> Architectural decisions governing this document are recorded in `00_foundations/02_Ticket_Architecture_Decisions_v1.md`. Where ambiguity exists, that document takes precedence.

## Core invariant

> **All person work activity must be tied to a Ticket.**

This applies to:

- Helpdesk/support incidents and requests (even when scope/effort is unknown).
- Project delivery work (WBS deliverables and sub-deliverables).
- Sold labour items (immutable `sold_minutes` on the ticket, set at quote acceptance).
- Internal / non-billable work (marketing, admin, R&D, management).
- Any time entry that reaches "submitted/approved/locked" status.

## Ticket is the universal work unit

A **Ticket** is the canonical, unique work container that holds:

- identity and ownership (customer/site/project/internal)
- lifecycle and operational state
- assignment and queueing linkage
- commercial context linkage (ad-hoc vs project vs agreement/SLA)
- optional SLA policy linkage
- WBS structure (parent/child) when applicable
- immutable sold baseline fields (`sold_minutes`, `sold_value_cents`) when created from an accepted quote

There must not be a parallel "work unit" that can receive time without passing through Ticket.

## Quote and ticket boundaries

### Quote draft phase (pre-acceptance)
- Quote labour tasks are managed entirely within the quote builder (`wp_pet_quote_tasks`).
- **No tickets are created during quoting.** Draft quote tasks are not commitments and do not belong in the ticket system.

### Quote acceptance boundary
- Acceptance freezes the quote snapshot (already the case).
- For each quote labour task, one ticket is created in `wp_pet_tickets` with:
  - `sold_minutes` locked to the accepted value (immutable from this point).
  - `is_baseline_locked = 1`.
  - `lifecycle_owner = 'project'`.
  - `status = 'planned'`.
- The accepted quote snapshot serves as the external audit record of "what was sold."
- The ticket's `sold_minutes` is the operational baseline for variance reporting.

**Rule:** After quote acceptance, sold fields on the ticket are never edited. Any change must be:
- a new ticket created as a change-order ticket, linked via `change_order_source_ticket_id`.

There is no separate "baseline ticket" record. There is no "execution ticket clone." One ticket per sold item.

## Single primary container, optional cross-links

Every ticket has exactly one **primary container authority**:

- Support (helpdesk)
- Project delivery
- Internal work

The ticket may have cross-links for reference (e.g., "support ticket assisting a project"), but:
- lifecycle authority is owned by the primary container
- commercial/billing authority is unambiguous and not double-counted

## Leaf-only time logging

Tickets may be structured as a WBS tree.

**Rule:** Time is logged against leaf tickets only. A ticket with no children is a leaf and may receive time directly. Once split into children, it becomes a rollup and no longer accepts direct time.

An unsplit sold ticket is a leaf and accepts time directly.

Rationale: prevents double counting; keeps time attribution precise.

## Baseline lock is a property, not a status

`is_baseline_locked` is a boolean field on the ticket, orthogonal to operational status.

A ticket can be both `in_progress` (operational status) and baseline-locked (commercial property). These do not conflict.

`baseline_locked` is NOT a lifecycle status.

## WBS depth and root semantics

- `parent_ticket_id` — immediate structural parent.
- `root_ticket_id` — always points to the top-level sold commitment ticket.
- Children can be split further (unlimited depth).
- Variance = `sold_minutes` on root minus `SUM(estimated_minutes)` on all leaves under that root.

## Change orders

Change orders create new tickets with their own `sold_minutes`, linked to the original via `change_order_source_ticket_id`.

Change order tickets are NOT children of the original. They are independent sold commitments with explicit traceability.

## Commercial defaults hierarchy (authoritative)

Defaults flow from:

Catalog → Quote Snapshot → Project Constraints → Ticket Defaults → Time Entry

Overrides must be explicit, auditable adjustments (not silent free edits).

## What "correct" looks like (end state)

- Quote acceptance creates one ticket per sold labour item with locked `sold_minutes`.
- Projects are composed of tickets (WBS tree via `parent_ticket_id` / `root_ticket_id`).
- Time entries always have `ticket_id` at submission boundary.
- Time is logged against leaf tickets only.
- Support and project tickets are the same entity; `lifecycle_owner` / `primary_container` is metadata, not a separate table.
- Change orders are new tickets linked by `change_order_source_ticket_id`, not mutations.
- No `ticket_mode` field. Classification uses `lifecycle_owner`, `ticket_kind`, and `is_baseline_locked`.
