# PET – Domain Model: State Machines

> **Ticket lifecycle note:** The authoritative lifecycle for tickets is defined in
> `docs/03_domain_model/03_Ticket_Lifecycle_and_State_Machines_v1.md`.
>
> This document describes generic state machine patterns used across PET domains.

## Purpose of this Document
This document defines state machines for PET domain entities other than Ticket.

For Ticket lifecycle semantics, see the authoritative document referenced above.

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
- intake (default on creation from quote or manual)
- planned
- active
- on_hold
- completed
- cancelled

Terminal states: completed, cancelled

Allowed transitions:
- intake → planned (scoped, PM assigned)
- intake → cancelled (abandoned before scoping)
- planned → active (work begins)
- planned → cancelled (abandoned before start)
- active → on_hold (paused)
- active → completed (delivered)
- active → cancelled (abandoned mid-delivery)
- on_hold → active (resumed)
- on_hold → cancelled (abandoned while paused)

Forbidden transitions:
- completed → any state
- cancelled → any state
- active → intake (no regression)
- active → planned (no regression)
- planned → intake (no regression)
- on_hold → intake (no regression)
- on_hold → planned (no regression)

Notes:
- `intake` is the initial state for all new projects (quote-sourced or manual).
- Archiving is handled via `archived_at` timestamp, not a state value.
- Transition validation is enforced in `ProjectState::canTransitionTo()`.
- `start_date` must not be set while a project is in `intake` state.
- Health scoring returns `grey` (unscored) for `intake` projects.
- No auto-promotion from `intake` to `planned` is permitted.

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

**This section is superseded.** The authoritative ticket lifecycle is defined in:
`docs/03_domain_model/03_Ticket_Lifecycle_and_State_Machines_v1.md`

That document governs all ticket status definitions, allowed transitions, and context-specific lifecycle rules for support, project, and internal tickets.

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

**Authority**: This document is normative for non-ticket state machines.
For ticket lifecycle authority, see `docs/03_domain_model/03_Ticket_Lifecycle_and_State_Machines_v1.md`.

