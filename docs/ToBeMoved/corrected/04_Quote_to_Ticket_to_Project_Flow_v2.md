# Quote to Ticket to Project Flow v2

STATUS: AUTHORITATIVE
VERSION: v2
OWNING LAYER: Commercial
SUPERSEDES: docs/07_commercial/04_Quote_to_Ticket_to_Project_Flow_v1.md
RELATED:
- docs/00_foundations/02_Ticket_Architecture_Decisions_v2.md
- docs/07_commercial/05_Sold_Ticket_Structural_Spec_v1.md
- docs/06_features/project_delivery.md
- docs/09_time/05_Time_Entry_Ticket_Enforcement_v1.md

## Purpose

This document defines the authoritative commercial-to-delivery handoff flow:
quote acceptance → sold baseline → project creation → ticket planning → execution.

It exists to make the commercial boundary explicit and to prevent ambiguity between commercial truth and delivery planning.

---

## 1. High-level principle

PET separates:

1. **commercial truth** — what was sold
2. **delivery planning** — how the work will be performed
3. **execution history** — what actually happened

These must never be collapsed into a single mutable record.

---

## 2. Preconditions for acceptance

A quote may be accepted only if the quote is in an allowed accept state per commercial rules.

Once accepted:
- the accepted quote version becomes immutable
- no in-place edits may rewrite sold terms
- subsequent commercial change uses additive mechanisms only

---

## 3. Acceptance transaction

Quote acceptance is a write-side transactional sequence.

Within the same transactional boundary, PET may create:

- accepted quote state transition
- quote-accepted event
- Project record
- project-context Tickets derived from sold labour scope
- due payment / milestone follow-on records where applicable

This creation happens exactly once per acceptance event.

Prohibited:
- duplicate creation on retries without idempotency protection
- render-side creation
- partial creation that leaves quote accepted but delivery baseline missing without explicit failure handling

---

## 4. Project creation rules

A project created from quote acceptance is the delivery container for that sold work.

Required characteristics:
- source_quote_id retained
- sold commercial context visible
- project may exist before PM assignment
- project creation does not imply work has started

Recommended initial project status:
- created or planning, depending on the chosen project-lifecycle design

If the broader project lifecycle is not yet implemented, the absence of that status model must not be worked around by starting tickets early.

---

## 5. Sold ticket creation rules

### 5.1 What becomes a sold ticket

Sold labour scope that requires managed delivery becomes project-context ticket baseline.

Each sold ticket must retain:
- commercial lineage to accepted quote/version
- sold_minutes
- sold_value_cents
- required_role_id or equivalent sold role snapshot where applicable
- lifecycle_owner = project
- initial status = planned

### 5.2 What does not become a project delivery ticket automatically

Not every commercial line must become the same type of operational object.

Examples needing explicit rule treatment:
- pure product delivery lines
- recurring services not governed by project delivery
- support entitlements governed by SLA/helpdesk rather than project execution

The existence of a commercial line does not justify flattening all downstream work into one operational type without context.

---

## 6. PM assignment after project creation

PM assignment belongs to Project, not the quote.

Rules:
- pm_employee_id may be null initially
- PM assignment is an explicit delivery governance action
- PM assignment does not mutate sold commercial baseline
- PM assignment does not itself start execution

Recommended event:
- ProjectPmAssigned

---

## 7. PM review and delivery planning

After creation, the PM reviews the sold scope through tickets.

The PM must be able to see:
- sold hours
- sold value
- required role intent
- current estimate
- current assignment
- current project lifecycle status

The PM may change operational planning fields only.
The PM may not change sold baseline fields.

---

## 8. WBS refinement

### 8.1 Why WBS exists

A sold ticket may be commercially valid but operationally too coarse.
PET therefore allows refinement into child tickets.

### 8.2 WBS rules

When split:
- original sold root remains traceable
- parent may become is_rollup = 1
- children inherit root lineage
- only leaf descendants may accept time
- sold baseline remains attached to the original sold root or another explicitly documented structural pattern; it must not disappear

### 8.3 Allocation and variance

Children may carry operational estimate allocations.
Variance must remain explicit.

Minimum visible relationships:
- root sold_minutes
- leaf estimated_minutes aggregate
- actual minutes aggregate

PET must not conceal oversubscription by rewriting sold baseline.

---

## 9. Readiness gate before execution

Commercial acceptance is not the same as execution readiness.

Required rule:
- project-context tickets created from a quote begin in planned
- they move to ready only after PM review / explicit readiness action

Prohibited:
- treating quote acceptance as automatic work start
- direct planned → done shortcuts
- time logging before lifecycle rules permit it

---

## 10. Execution and actuals

Execution begins only through explicit project-ticket lifecycle transition.

Actuals come from immutable time entries.
Actuals do not rewrite sold or estimated values.

Derived management views may compare:
- sold vs estimated
- sold vs actual
- estimated vs actual

---

## 11. Change after sale

If scope changes after sale, PET must use additive commercial mechanisms.

Examples:
- change order
- additional sold ticket
- variance record
- commercial adjustment under the approved governance model

Prohibited:
- silently changing sold_minutes on the original sold ticket
- rewriting quote history to match delivery reality
- using operational estimate edits as commercial change

---

## 12. Support / SLA boundary

Support work and SLA-governed work are not automatically project-delivery work.

Rules:
- support entitlements remain in helpdesk/SLA context unless an explicit delivery mechanism creates linked project work
- support incidents may link to project remediation tickets
- linkage does not rewrite lifecycle_owner

This boundary prevents accidental lifecycle collapse between helpdesk and delivery.

---

## 13. Delivery handoff summary

The canonical sequence is:

1. Quote enters accepted state.
2. Acceptance transaction creates project baseline records.
3. Project-context tickets are created in planned state.
4. PM assignment occurs explicitly.
5. PM reviews sold scope.
6. PM may split/refine into WBS.
7. Tickets move to ready.
8. Work begins through explicit execution transition.
9. Actuals accrue via immutable time records.
10. Post-sale change is additive, never destructive.

---

## 14. Prohibited behaviours

The following are prohibited:

- quote acceptance that rewrites historical quote content
- project creation on read/render
- ticket creation on read/render
- automatic execution start on acceptance
- WBS refinement that destroys sold-root lineage
- support/SLA work silently converted into project work
- use of estimate edits to hide commercial variance
- dual-authority between legacy task records and ticket records for new delivery work

---

## 15. Authority statement

For commercial-to-delivery handoff, this document is authoritative.
Feature or UI docs may describe screens and interactions, but they must not redefine the core handoff sequence or mutate the commercial boundary defined here.
