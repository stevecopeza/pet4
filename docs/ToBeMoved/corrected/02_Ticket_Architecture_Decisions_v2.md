# Ticket Architecture Decisions v2

STATUS: AUTHORITATIVE
VERSION: v2
OWNING LAYER: Foundations
SUPERSEDES: docs/00_foundations/02_Ticket_Architecture_Decisions_v1.md
RELATED:
- docs/00_foundations/01_Ticket_Backbone_Principles_and_Invariants_v1.md
- docs/03_domain_model/03_Ticket_Lifecycle_and_State_Machines_v1.md
- docs/07_commercial/04_Quote_to_Ticket_to_Project_Flow_v1.md
- docs/06_features/project_delivery.md

## Purpose

This document records the binding architecture decisions for PET’s ticket backbone.
Where other documents describe workflows, UI, or implementation detail, this document controls the core shape of the model.

If another document conflicts with a decision here, this document wins unless a later-numbered replacement explicitly supersedes it.

---

## Decision 1 — Ticket is the single operational work object

PET uses **Ticket** as the single operational work object for both support and project delivery contexts.

Implications:
- PET must not maintain separate operational truth objects for “project task” versus “support ticket”.
- Legacy task constructs may exist temporarily for backward compatibility, but they are not the long-term operational authority.
- New delivery planning and execution must be anchored to Ticket.

Non-goals:
- This decision does not mean support and project tickets share the same lifecycle rules in every respect.
- This decision does not permit context mixing. Context-specific state rules remain explicit.

---

## Decision 2 — Commercial baseline and operational planning are distinct

A sold commercial baseline is not the same thing as an operational execution plan.

Implications:
- sold_minutes and sold_value_cents are immutable baseline fields
- estimated_minutes is an operational planning field
- actuals derive from immutable time records
- planning may evolve, but sold truth may not be rewritten

PET must never hide sold baseline once work enters delivery.

---

## Decision 3 — Accepted commercial records are immutable

Once a quote is accepted:
- the accepted quote version is immutable
- sold ticket baseline fields created from that quote are immutable
- corrections are additive only

Prohibited:
- editing sold minutes in place
- editing sold value in place
- rewriting historical accepted quote data
- silently reallocating sold baseline after acceptance

Changes in scope must be represented as new commercial records and, where applicable, new delivery tickets.

---

## Decision 4 — Quote acceptance creates delivery-operational records

Accepting a quote may create:
- a Project
- one or more project-context Tickets
- due commercial follow-on records such as payment milestone triggers

This is a write-side creation flow.
Render-side access must never create operational records.

---

## Decision 5 — Project and support contexts are separate ticket lifecycles

Ticket is the shared work object, but lifecycle ownership is explicit.

Allowed lifecycle_owner values include:
- support
- project

Implications:
- support workflows use support rules
- project workflows use project-delivery rules
- support tickets are not converted into project children by convenience
- cross-links are permitted; lifecycle fusion is not

---

## Decision 6 — WBS refinement happens by ticket hierarchy, not by baseline mutation

A coarse sold ticket may be refined into a work breakdown structure using ticket hierarchy.

Rules:
- parent_ticket_id links the immediate parent
- root_ticket_id points to the original delivery root
- is_rollup marks non-leaf coordination/aggregation tickets
- only leaf tickets may accept time

Prohibited:
- splitting by mutating sold baseline into multiple rewritten baseline rows
- allowing rollup tickets to accept time entries
- destroying lineage from child ticket back to original sold root

---

## Decision 7 — Role intent and person assignment are separate concepts

Required role and assigned person are not interchangeable.

Recommended operational model:
- required_role_id expresses delivery intent / skill requirement
- assigned_employee_id expresses execution responsibility

Prohibited:
- treating person assignment as a rewrite of role requirement
- losing sold role intent when a person is assigned
- inferring commercial baseline from current staff assignment

---

## Decision 8 — Variance is visible, not hidden

Variance between sold baseline, operational estimate, and actual effort must remain explicit.

Minimum derived views:
- sold vs estimated
- sold vs actual
- estimated vs actual

Prohibited:
- “fixing” overrun by editing sold baseline
- collapsing variance into a rewritten estimate without trace
- hiding baseline after planning refinement

This decision does not force a single enforcement policy for oversubscription; it requires that any oversubscription be explicit and auditable.

---

## Decision 9 — Project delivery starts from reviewable planning state

Tickets created from a sold quote enter a planning-oriented state first, not active execution.

Default project-context entry state:
- planned

This creates room for PM review, assignment, refinement, and WBS expansion before execution begins.

---

## Decision 10 — Time may only be logged against executable leaves

A ticket may accept time only if it is an executable leaf.

A ticket must reject time if:
- it is a rollup
- it has child tickets
- it is in a non-executable state according to lifecycle rules

This decision is mandatory for both support and project contexts.

---

## Decision 11 — Project ownership belongs to Project, not to quote

A project may have a PM owner, but PM assignment belongs to the Project aggregate, not to the original quote.

Recommended field:
- pm_employee_id (nullable until assigned)

Reason:
- commercial ownership and delivery ownership are related but not identical
- project governance requires a delivery owner without rewriting commercial history

---

## Decision 12 — Project activation and ticket readiness are distinct

Project-level readiness and ticket-level readiness are not the same concept.

Implications:
- a project may exist in planning while tickets remain planned
- tickets may be moved to ready individually
- project-level lifecycle, if present, must not collapse ticket-level lifecycle history

---

## Decision 13 — Cross-context relationships use links, not hierarchy abuse

Where support work and project work are related:
- use explicit relation/link semantics
- do not force one into the other’s tree unless the domain truly requires parent/child execution lineage

Examples:
- support incident linked to delivery remediation ticket
- project ticket linked to originating support escalation
- advisory outputs linked to both, without mutating operational truth

---

## Decision 14 — Read-side views must not have write-side side effects

The following must never happen during render or read:
- automatic project creation
- automatic ticket creation
- automatic WBS splitting
- automatic PM assignment
- automatic advisory/escalation creation unrelated to explicit write rules

All such behaviour must be command-driven and auditable.

---

## Decision 15 — Backward compatibility is transitional, not authoritative

Temporary coexistence with legacy task entities or screens is permitted only as a transition aid.

Rules:
- new documentation and new implementation must anchor to Ticket
- legacy task paths must not become the reference model
- any temporary bridge must preserve ticket authority and historical safety

---

## Decision Summary

PET’s architecture is therefore:

1. Quote acceptance freezes commercial truth.
2. Delivery records are created additively from that truth.
3. Ticket is the operational work object.
4. Project and support are separate lifecycle contexts on the shared ticket backbone.
5. Planning may evolve through hierarchy and assignments.
6. Sold baseline and historical execution remain immutable.

This is the binding foundation for commercial handoff, project delivery, helpdesk, and SLA interaction docs.
