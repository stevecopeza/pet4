# PET – Domain Model: State Machines

## Purpose of this Document
This document defines the **authoritative state machines** for PET domain entities.

It specifies:
- Allowed states
- Permitted transitions
- Forbidden transitions
- Mandatory resolution points

All state transitions are enforced via **hard blocks** by default.

---

## Global State Machine Rules

- State transitions are explicit, never implicit
- Illegal transitions are **blocked immediately**
- Errors must be resolved before continuation
- No background or deferred correction is permitted

State machines are enforced in the **domain layer**.

---

## Lead State Machine

States:
- new
- qualified
- converted
- disqualified
- archived

Allowed transitions:
- new → qualified
- new → disqualified
- qualified → converted (triggers Quote creation with `lead_id` link)
- qualified → disqualified
- converted → archived
- disqualified → archived

Forbidden transitions:
- converted → qualified (cannot un-convert)
- disqualified → qualified
- archived → Any

Notes:
- Conversion to `converted` sets `convertedAt` timestamp
- Conversion creates a new Quote linked via `lead_id`
- Direct `new` → `converted` is permitted (skip qualification for simple leads)

---

## Qualification State Machine
**STATUS: NOT YET IMPLEMENTED — Planned**

States:
- Started
- Completed
- Archived

Allowed transitions:
- Started → Completed
- Completed → Archived

Forbidden transitions:
- Archived → Any

---

## Opportunity State Machine
**STATUS: NOT YET IMPLEMENTED — Planned**

States:
- Created
- Active
- Quoted
- Won
- Lost
- Archived

Allowed transitions:
- Created → Active
- Active → Quoted
- Quoted → Won
- Quoted → Lost
- Won → Archived
- Lost → Archived

Forbidden transitions:
- Lost → Won
- Archived → Any

---

## Quote State Machine

States:
- draft
- sent
- accepted
- rejected
- archived

Allowed transitions:
- draft → sent
- draft → archived
- sent → accepted
- sent → rejected
- sent → draft (revert to draft)
- sent → archived

Terminal states: accepted, rejected, archived

Forbidden transitions:
- accepted → Any
- rejected → Any
- archived → Any

Notes:
- `send()` validates readiness (components, payment schedule, margin, title)
- `accept()` validates readiness then triggers QuoteAccepted event chain
- Any modification attempt on terminal-state quotes throws DomainException
- Changes require new Quote entities (deep clone / versioning)

---

## Sale State Machine
**STATUS: NOT YET IMPLEMENTED — Planned**

States:
- Created
- Fulfilled
- Archived

Allowed transitions:
- Created → Fulfilled
- Fulfilled → Archived

Forbidden transitions:
- Fulfilled → Created

Note: Currently the QuoteAccepted event chain handles sale-equivalent behaviour.

---

## Project State Machine

States:
- Created
- Planned
- Active
- Completed
- Cancelled
- Archived

Allowed transitions:
- Created → Planned
- Planned → Active
- Active → Completed
- Active → Cancelled
- Completed → Archived
- Cancelled → Archived

Forbidden transitions:
- Completed → Active
- Cancelled → Active

---

## Milestone State Machine

States:
- Defined
- Active
- Completed
- Archived

Allowed transitions:
- Defined → Active
- Active → Completed
- Completed → Archived

Forbidden transitions:
- Completed → Active

---

## Task State Machine

States:
- Planned
- In Progress
- Completed
- Archived

Allowed transitions:
- Planned → In Progress
- In Progress → Completed
- Completed → Archived

Forbidden transitions:
- Completed → In Progress

Additional rules:
- Time logging is forbidden on Completed or Archived tasks

---

## Time Entry State Machine

States:
- Draft
- Submitted
- Locked

Allowed transitions:
- Draft → Submitted
- Submitted → Locked

Forbidden transitions:
- Submitted → Draft
- Locked → Any

---

## Ticket State Machine

Tickets use context-specific state machines, enforced by `TicketStatus` value object.

### Support (lifecycle_owner='support')
States: new, open, pending, resolved, closed

Allowed transitions:
- new → open
- open → pending
- open → resolved
- pending → open
- pending → resolved
- resolved → closed

### Project (lifecycle_owner='project')
States: planned, ready, in_progress, blocked, done, closed

Allowed transitions:
- planned → ready
- ready → in_progress
- in_progress → blocked
- in_progress → done
- blocked → in_progress
- done → closed

### Internal (lifecycle_owner='internal')
States: planned, in_progress, done, closed

Allowed transitions:
- planned → in_progress
- in_progress → done
- done → closed

Forbidden transitions (all contexts):
- Any transition from `closed` (terminal state)
- Cross-context transitions (e.g. support status on a project ticket)

---

## SLA State Machine

States:
- Defined
- Active
- Expired
- Archived

Allowed transitions:
- Defined → Active
- Active → Expired
- Expired → Archived

Forbidden transitions:
- Expired → Active

---

## Knowledge Article State Machine

States:
- Draft
- Published
- Revised
- Archived

Allowed transitions:
- Draft → Published
- Published → Revised
- Revised → Published
- Published → Archived

Forbidden transitions:
- Archived → Any

---

## Error Handling Policy

When an illegal transition is attempted:

- The action is blocked
- The error is explicit
- The user must resolve the issue immediately

No silent fallback is permitted.

---

**Authority**: Normative

This document defines all allowed state transitions in PET. Implementation must conform.

