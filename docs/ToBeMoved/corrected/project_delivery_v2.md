# Project Delivery v2

STATUS: AUTHORITATIVE FOR FEATURE USAGE
VERSION: v2
OWNING LAYER: Features
SUPERSEDES: docs/06_features/project_delivery.md
RELATED:
- docs/00_foundations/02_Ticket_Architecture_Decisions_v2.md
- docs/03_domain_model/03_Ticket_Lifecycle_and_State_Machines_v2.md
- docs/07_commercial/04_Quote_to_Ticket_to_Project_Flow_v2.md

## Purpose

This document defines how PET uses the ticket backbone to run project delivery.
It deliberately replaces older project-delivery descriptions that imply a separate task truth model.

This document governs feature behaviour, not low-level storage or API implementation detail.

---

## 1. Delivery object model

Project delivery uses:

- **Project** as the delivery container and governance owner
- **Ticket** as the operational work object
- **Ticket hierarchy** for WBS refinement
- **Time entries** as immutable actual execution records

Legacy task structures may remain temporarily for backward compatibility, but they are not the target feature model for new delivery behaviour.

---

## 2. Core delivery principle

Project delivery must always show the relationship between:

- sold baseline
- current plan
- actual execution

PET must never show project delivery as if it were only a mutable task list detached from sold scope.

---

## 3. Delivery lifecycle at feature level

### 3.1 Project

A project is created from accepted commercial scope or another explicit creation mechanism.

The project may hold:
- customer context
- source quote lineage
- PM ownership
- aggregate health/progress
- delivery-level status

### 3.2 Tickets

Tickets carry executable delivery scope.

Project tickets begin in planning-oriented state.
They do not start in active execution.

### 3.3 WBS

Where needed, a PM may split a coarse sold ticket into child tickets.
The hierarchy is a planning/refinement tool and must preserve lineage to sold root.

---

## 4. PM responsibilities

The PM is responsible for:

- reviewing sold delivery scope
- assigning or refining delivery ownership
- turning coarse sold scope into workable execution plan where needed
- moving tickets to ready when appropriately reviewed
- monitoring variance and blockers
- closing delivery work without rewriting commercial truth

The PM is not permitted to:
- change sold baseline in place
- hide overrun by editing baseline
- bypass lifecycle controls for convenience

---

## 5. Project detail screen expectations

The project delivery UI should show tickets, not legacy tasks, as the primary operational rows.

Minimum expectations:
- sold baseline visible
- estimate visible
- actual visible or derivable
- status visible
- assignment visible
- blocker visibility
- split / WBS access where supported

Prohibited:
- task-only delivery views for newly accepted work
- hiding sold baseline after acceptance
- mixing task truth and ticket truth in the same execution flow without explicit migration design

---

## 6. Planning rules

Planning may evolve.
Commercial truth may not.

Allowed PM planning actions include:
- setting or adjusting estimated effort
- assigning people
- assigning or preserving role requirements
- splitting tickets into children
- marking readiness for execution
- managing blocked work

Not allowed:
- changing sold hours in place
- changing sold value in place
- deleting sold lineage when refining scope

---

## 7. Execution rules

Execution begins only once a ticket is in an executable lifecycle state according to the authoritative ticket lifecycle document.

Time logging:
- only on leaves
- only when lifecycle allows
- always as additive historical record

Execution dashboards must distinguish:
- not started planning
- ready
- in progress
- blocked
- done
- closed

---

## 8. Variance and health

Project delivery features must surface variance explicitly.

Minimum derived signals:
- sold vs estimated
- sold vs actual
- estimated vs actual
- blocked work visibility
- progress by executable leaves

Project health may derive from these signals, but health indicators must not replace visibility into the underlying numbers.

---

## 9. Support / helpdesk interaction

Project delivery and support/helpdesk are related but distinct.

Allowed patterns:
- support incident linked to delivery remediation ticket
- project ticket linked to support-origin insight
- escalations or advisory outputs referencing delivery context

Not allowed:
- treating support queues as project WBS by convenience
- moving support tickets into project hierarchy without explicit domain rule
- using SLA timers as the delivery execution engine

---

## 10. Project activation gate

Project delivery benefits from an explicit planning-to-active boundary.

Recommended model:
- project created/planning
- project active when delivery is ready to begin
- project completed when executable work is done
- project closed when governance/administrative closure is finished

If the project-level state machine is not yet fully implemented, the UI must still preserve the distinction conceptually and must not imply immediate execution on sale.

---

## 11. Prohibited behaviours

The following are prohibited:

- using legacy task creation as the normal write path for new delivery work
- rendering project detail from task truth while ticket truth exists for the same work
- hiding sold baseline
- mutating baseline to match actuals
- allowing rollup tickets to accept time
- starting execution from quote acceptance by side effect
- using assignment as implicit lifecycle transition

---

## 12. Authority statement

For feature-level project delivery behaviour, this document is authoritative.
If older feature notes conflict with the ticket-based delivery model here, this document wins.
