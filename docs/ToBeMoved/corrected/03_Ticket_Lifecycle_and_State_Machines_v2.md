# Ticket Lifecycle and State Machines v2

STATUS: AUTHORITATIVE
VERSION: v2
OWNING LAYER: Domain Model
SUPERSEDES: docs/03_domain_model/03_Ticket_Lifecycle_and_State_Machines_v1.md
RELATED:
- docs/00_foundations/02_Ticket_Architecture_Decisions_v2.md
- docs/09_time/05_Time_Entry_Ticket_Enforcement_v1.md
- docs/06_features/project_delivery.md
- docs/06_features/helpdesk_and_sla.md

## Purpose

This document defines the authoritative lifecycle model for Ticket.
It replaces fragmented or generic state-machine descriptions wherever Ticket-specific behaviour is concerned.

Generic state-machine notes may still exist elsewhere, but they are not authoritative for Ticket unless they explicitly defer to this document.

---

## 1. Lifecycle ownership

Every ticket belongs to exactly one lifecycle context at a time.

Primary contexts:
- support
- project

A ticket’s lifecycle rules derive from lifecycle_owner.
State names may overlap across contexts, but transition permission is context-specific.

---

## 2. Shared invariants across all ticket contexts

These invariants always apply:

1. Every ticket has exactly one lifecycle_owner.
2. Every ticket has exactly one current status.
3. Time may be logged only to executable leaves.
4. Historical baseline fields are never edited to conceal execution reality.
5. Illegal transitions must hard-fail.
6. Read-side access may not cause lifecycle mutation.
7. Child/parent hierarchy does not override lifecycle validation.

---

## 3. Project-context lifecycle

### 3.1 Canonical project statuses

Project-context ticket statuses are:

- planned
- ready
- in_progress
- blocked
- done
- closed

### 3.2 Meaning of each state

**planned**
- ticket exists as delivery planning scope
- not yet cleared for execution
- may be refined, split, assigned, or re-estimated within the allowed model

**ready**
- ticket is sufficiently reviewed and can begin execution
- ticket may still remain unstarted

**in_progress**
- active delivery work is underway

**blocked**
- execution is paused due to dependency, decision, missing input, access issue, or other explicit blocker

**done**
- execution work is completed

**closed**
- ticket is administratively complete and no further operational work should occur

### 3.3 Allowed project transitions

Allowed transitions:

- planned → ready
- ready → in_progress
- ready → blocked
- in_progress → blocked
- blocked → ready
- blocked → in_progress
- in_progress → done
- done → closed

Allowed correction / rollback transitions only where domain rules explicitly permit:
- ready → planned (only before time is logged, and only as a planning correction if implemented)
- done → in_progress is disallowed by default unless a documented reopen policy is introduced
- closed → any other state is disallowed by default

### 3.4 Project-context prohibited transitions

Not allowed:
- planned → done
- planned → closed
- planned → in_progress without readiness gate
- ready → closed
- blocked → done without resumed execution path
- closed → anything
- parent/rollup-only tickets bypassing lifecycle gates for leaf execution

### 3.5 Time logging rules in project context

Time logging is allowed only when:
- ticket is a leaf
- ticket is executable
- status is in_progress or another explicitly allowed execution status if later introduced

Time logging is not allowed in:
- planned
- ready
- blocked
- done
- closed
- any rollup state

---

## 4. Support-context lifecycle

PET may support a richer or different support ticket lifecycle than project delivery.
This document does not attempt to redefine every helpdesk nuance if the dedicated support docs do so more specifically.

However, the following minimum rules are binding:

1. support lifecycle is separate from project lifecycle
2. support tickets must not be forced into project statuses by shared UI shortcuts
3. SLA timing and breach logic operate against support semantics, not project semantics
4. support-to-project linkage does not mutate lifecycle_owner

Where a dedicated support/helpdesk lifecycle document exists, it may add support-specific statuses and transitions, but it may not contradict these boundaries.

---

## 5. Hierarchy interaction rules

Hierarchy does not create a new lifecycle.
It changes executability and rollup semantics only.

Rules:
- a parent may remain planned or ready while children progress independently if the UI/queries support that view
- a rollup ticket must not accept time
- closure of a parent should be derived or guarded by child completion rules, not by convenience

Recommended guard:
- a parent should not enter done or closed while executable descendants remain unfinished

---

## 6. Assignment interaction rules

Assignment does not itself change lifecycle status.

Examples:
- assigning a PM does not move a ticket to ready
- assigning an employee does not automatically start work
- assigning a role does not imply execution started

Lifecycle changes require explicit write-side commands.

---

## 7. Commercial interaction rules

Commercial acceptance creates project-context tickets in planned state.

Implications:
- quote acceptance does not directly start execution
- PM review/refinement happens after commercial handoff
- sold baseline visibility remains throughout lifecycle

---

## 8. Blocked state semantics

Blocked is not a cosmetic flag. It is a real operational state.

Minimum expectations:
- reason should be explicit where UI/API allows it
- blocked work remains visible in delivery dashboards
- blocked work must not be silently treated as in progress
- transitions out of blocked must be explicit

---

## 9. Done vs closed

Done means execution work is complete.
Closed means the ticket is fully administratively finished.

Reason for the distinction:
- preserves room for QA, handoff, sign-off, or governance completion
- avoids collapsing execution completion and record completion into one event

If a simplified UI chooses not to expose both prominently, the backend lifecycle still must respect the distinction.

---

## 10. Reopen policy

By default:
- closed tickets do not reopen
- done tickets do not reopen automatically

If PET later introduces reopen behaviour, it must:
- be explicit
- be audited
- not destroy historical time or baseline
- define allowed transitions precisely

Until such a document exists, reopen behaviour is out of scope.

---

## 11. Prohibited lifecycle behaviours

The following are prohibited:

- state mutation on read/render
- auto-progression from planned to in_progress without explicit command
- using assignment as an implicit status change
- allowing time on non-leaf tickets
- using support SLA events to mutate project ticket status by side effect
- using UI convenience actions to bypass domain validation
- hiding blocked state by treating it as “soft in progress”

---

## 12. Authority statement

For Ticket lifecycle semantics:
- this document is authoritative
- generic state-machine notes must defer to this document
- feature docs may describe how the lifecycle is used, but not redefine it

Any dedicated support/helpdesk lifecycle extension must be read as a constrained specialization of this document, not a replacement of it.
