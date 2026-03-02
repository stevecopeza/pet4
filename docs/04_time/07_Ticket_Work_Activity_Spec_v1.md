STATUS: IMPLEMENTED
SCOPE: Work Activity Logging within Ticket Context
VERSION: v1.1

# Ticket Work Activity Spec (v1)

## Purpose

Defines how work activity (time entries) is created, displayed, and managed within the context of a ticket. Bridges the gap between:

- `05_Time_Entry_Ticket_Enforcement_v1.md` (submission rules)
- `06_Time_Entry_Work_Log_Display_v1.md` (rendering)
- `docs/03_cross_cutting_concerns/04_time_and_resource_accounting.md` (domain principles)
- `docs/07_work_orchestration/08_Work_Orchestration_Queues_and_Assignment_v1.md` (assignment)

## Core Concept

A time entry is not a "timer" — it is a factual record of **"this is what I did"** against a ticket, with time associated. The primary artifact is the work description; duration is an attribute of that work.

---

## 0. Context Independence

Everything in this spec applies equally to **all ticket contexts**. Tickets are the universal work unit (per `00_foundations/01_Ticket_Backbone_Principles_and_Invariants_v1.md`). Support tickets, project tickets, and internal tickets are the same entity in the same table (`wp_pet_tickets`), differentiated only by `primary_container` ENUM('support','project','internal').

This means:
- A support agent logging 20 minutes on an incident and an engineer logging 3 hours on a project deliverable use the **same mechanism**: create a time entry against a ticket.
- Assignment (queueId / ownerUserId) works identically regardless of container type.
- The draft → submitted → locked lifecycle is context-agnostic.
- The Work Log section in the ticket detail view renders the same way for all contexts.
- Billable defaults may differ by context (SLA-linked vs project-linked vs internal), but the override and correction model is identical.

### Projects as ticket collections

A project is composed of tickets structured as a WBS tree (via `parent_ticket_id` / `root_ticket_id`). Phases and milestones group tickets; they do not replace them. The leaf-only time logging rule applies:

- **Leaf tickets** (no children): accept time entries directly.
- **Roll-up tickets** (`is_rollup = 1`, have children): may NOT accept time entries. Their totals are computed by aggregating from leaves.
- This prevents double-counting and preserves baseline integrity.

When a project ticket is the current view, the "Log Work" flow is identical to a support ticket — the user describes what they did, provides duration, sets billable. The only difference is how the billable default is derived (from project/quote context rather than SLA context).

### Internal tickets

Internal work (admin, R&D, management) uses the same ticket and time entry model. These tickets typically default to non-billable, but an employee may override if the work is chargeable.

---

## 1. Assignment vs Work Authority

These are explicitly separate concepts.

**Assignment** = operational responsibility. Who is driving the ticket toward resolution.
- Stored on ticket as `ownerUserId` (individual) and `queueId` (team)
- One owner at a time (nullable — may be unassigned)
- One queue/team at a time (mandatory per helpdesk addendum v1.1)
- Changed via assign/reassign/pull commands

**Work authority** = who may log work against a ticket.
- Any employee may log time against any open ticket, regardless of assignment
- The person logging work does not need to be the assigned owner
- This is intentional: escalations, collaboration, handoffs, and specialist involvement are normal

**Rationale:** In practice, a support ticket may be owned by Noah but Liam does 30 minutes of specialist investigation. Both need to record their work. Assignment tracks responsibility; time entries track reality.

---

## 2. Work Activity Logging Flow (Ticket Context)

When a user is viewing a ticket detail panel and wants to record work:

### Implicit context (no selection required)
- **Ticket**: the ticket being viewed (immutable on the entry)
- **Employee**: the current logged-in user

### User provides
- **Description** (required): what was done — free text, meaningful enough for review
- **Duration or time range** (required): either minutes/hours, or start + end time
- **Billable** (required, defaults based on ticket context): whether this work is chargeable
  - Default derives from ticket's commercial context: SLA/agreement tickets default to billable; internal tickets default to non-billable
  - User may override at entry time

### Created as
- Status: `draft`
- The entry is immediately visible in the Work Log

### What this is NOT
- Not a running timer (though a timer UX could create the entry on stop)
- Not a mutation of the ticket itself
- Not an assignment change

---

## 3. Time Entry Lifecycle

```
Draft → Submitted → Locked
```

### Draft
- Created by the employee who did the work
- Editable by the **creating employee only**: description, duration, billable flag
- `ticketId` and `employeeId` are immutable from creation
- May be deleted by the creating employee (soft delete via `archivedAt`)

### Submitted
- Represents the employee's attestation: "this is accurate"
- Immutable — no edits permitted
- `ticket_id` must be present (enforced per `05_Time_Entry_Ticket_Enforcement_v1.md`)
- Emits `TimeEntrySubmitted` domain event

### Locked
- Approved/finalised by a manager or automated process
- Permanently immutable
- Eligible for billing export
- Commercial context snapshot captured (rate, agreement drawdown)

### Corrections (submitted or locked entries)
- Original entry is preserved — never edited or deleted
- A compensating entry is created referencing the original via `corrects_entry_id`
- Compensating entry may have negative duration (reversal) or different classification
- Implemented: `TimeEntry::createCorrection()`, `createReversal()`, `POST /time-entries/{id}/correct`

---

## 4. Ticket Assignment Operations

The ticket's assignment determines operational responsibility but does NOT restrict who can log work.

### Assign to team
- Sets `queueId` on ticket
- Clears `ownerUserId` (ticket is now "in queue" but unowned)
- Emits assignment event

### Assign to employee
- Sets `ownerUserId` to specified employee
- `queueId` remains (team context preserved)
- Emits assignment event

### Pull (self-assign)
- Sets `ownerUserId` to the requesting employee
- Equivalent to "assign to employee" where employee = current user
- Used by agents pulling from the unassigned queue

### Reassign
- Sets `ownerUserId` to a different employee
- Previous owner loses responsibility but their logged time entries remain intact
- No time entries are moved or deleted — they are factual records

### "Work on it even though it's assigned to someone else"
- Permitted by design — any employee can log time against any open ticket
- The Work Log shows all entries with the actual employee who did the work
- Assignment is about responsibility, not about locking access

---

## 5. Billable Defaults Hierarchy

When creating a time entry from a ticket context, the billable default is derived from the ticket's commercial context:

1. **Support ticket with SLA/agreement** (`billing_context_type` = 'agreement') → default billable = true
2. **Project ticket with sold hours** (`billing_context_type` = 'project', `sold_minutes` > 0) → default billable = true
3. **Ad-hoc support ticket** (`billing_context_type` = 'adhoc') → default billable = true
4. **Internal ticket** (`billing_context_type` = 'internal') → default billable = false
5. Fallback: use ticket's `is_billable_default` field (set at creation from commercial context)

The user may always override at entry time. Reclassification after submission requires a compensating entry.

---

## 6. Multiple Workers Model

A single ticket may accumulate time entries from multiple employees. This is normal and expected.

The Work Log section in the ticket detail view shows ALL entries regardless of:
- Who is currently assigned
- Whether the employee is still active
- Whether the entry is draft, submitted, or locked

Summary statistics (total hours, billable hours) aggregate across all employees.

Per-employee breakdowns are a future reporting concern, not a Work Log display concern.

---

## 7. Implementation Status

### Exists
- TimeEntry entity with draft/submitted/locked lifecycle
- LogTimeCommand + LogTimeHandler (creates draft entries)
- SubmitTimeEntryCommand + SubmitTimeEntryHandler
- GET /time-entries?ticket_id=X (read)
- POST /time-entries (create) — accepts employeeId, ticketId, start, end, isBillable, description
- PUT /time-entries/{id} — update draft entries (description, duration, billable)
- Work Log display in ticket detail view with inline "Log Work" form and draft editing
- Ticket entity has queueId + ownerUserId fields
- UpdateDraftTimeEntryCommand + UpdateDraftTimeEntryHandler (domain guard: non-draft rejects)
- Ticket domain methods: assignToTeam(), assignToEmployee(), pull() with domain events
- TicketAssigned event with previousOwnerUserId, previousQueueId, newQueueId fields
- Assignment API: POST /tickets/{id}/assign/team, /assign/employee, /pull
- Assignment syncs to WorkItem projection (assigned_user_id + department_id)
- Frontend assignment controls in ticket detail sidebar (employee dropdown, queue dropdown, pull button)

### Needs implementation
- Full event-sourced dispatching (currently sync in controller; projector listener wired but not called via event bus)

---

## 8. Invariants (Binding)

- A time entry's `ticketId` is set at creation and NEVER changes
- A time entry's `employeeId` is set at creation and NEVER changes
- Assignment does not restrict who may log time
- Submitted and locked entries are immutable
- Corrections are compensating entries, not edits
- The Work Log shows factual truth — all entries, all employees, all statuses

---

## Related Documents

- `04_time/05_Time_Entry_Ticket_Enforcement_v1.md` — submission boundary rules
- `04_time/06_Time_Entry_Work_Log_Display_v1.md` — rendering contract
- `03_cross_cutting_concerns/04_time_and_resource_accounting.md` — domain principles
- `04_features/docs_04_features_timesheets_ux.md` — standalone timesheet UX
- `07_work_orchestration/08_Work_Orchestration_Queues_and_Assignment_v1.md` — queue mechanics
- `00_foundations/01_Ticket_Backbone_Principles_and_Invariants_v1.md` — ticket as universal work unit
