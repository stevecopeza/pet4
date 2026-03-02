STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# Ticket Backbone — Principles and Invariants (v1)

## Core invariant

> **All person work activity must be tied to a Ticket.**

This applies to:

- Helpdesk/support incidents and requests (even when scope/effort is unknown).
- Project delivery work (WBS deliverables and sub-deliverables).
- Quoted labour items (draft state pre-acceptance; immutable baseline post-acceptance).
- Internal / non-billable work (marketing, admin, R&D, management).
- Any time entry that reaches “submitted/approved/locked” status.

## Ticket is the universal work unit

A **Ticket** is the canonical, unique work container that holds:

- identity and ownership (customer/site/project/internal)
- lifecycle and operational state
- assignment and queueing linkage
- commercial context linkage (ad-hoc vs project vs agreement/SLA)
- optional SLA policy linkage
- WBS structure (parent/child) when applicable

There must not be a parallel “work unit” that can receive time without passing through Ticket.

## “Draft vs immutable” boundaries

### Quote draft phase (pre-acceptance)
- Quote labour items **create draft tickets immediately**.
- Draft quote tickets are mutable while the quote is editable.

### Quote acceptance boundary
- Acceptance freezes the quote baseline.
- Baseline becomes immutable history: “what was sold”.
- Project execution may evolve without mutating the baseline.

**Rule:** After quote acceptance, baseline sold work is never edited. Any change must be:
- a new ticket created as a change-order / variance ticket, or
- an additive adjustment record tied to the baseline ticket(s).

## Single primary container, optional cross-links

Every ticket has exactly one **primary container authority**:

- Support (helpdesk)
- Project delivery
- Internal work

The ticket may have cross-links for reference (e.g., “support ticket assisting a project”), but:
- lifecycle authority is owned by the primary container
- commercial/billing authority is unambiguous and not double-counted

## Leaf-only time logging

Tickets may be structured as a WBS tree.

**Rule:** Time is logged only against **leaf tickets**.  
If a ticket has children, it becomes a **roll-up container** and may not accept time entries.

Rationale: prevents double counting; preserves baseline containers.

## Commercial defaults hierarchy (authoritative)

Defaults flow from:

Catalog → Quote Snapshot → Project Constraints → Ticket Defaults → Time Entry

Overrides must be explicit, auditable adjustments (not silent free edits).

## What “correct” looks like (end state)

- Quote labour → Ticket (draft) → Ticket (baseline) + Ticket (execution) mapping is explicit.
- Projects are primarily composed of tickets (WBS).
- Time entries always have `ticket_id` at submission boundary.
- Support and project tickets are the same entity; “type/context” is metadata, not a separate table.
