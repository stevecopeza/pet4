# PET Ticket Backbone Correction — Final Spec (Tickets-Only Model)

## Purpose
Replace Task-based delivery execution with a Tickets-only model. Tasks are removed from execution entirely to eliminate ambiguity and enforce a single operational backbone.

---

## Core Rule
Tickets are the ONLY execution entity.
Tasks must not be created, referenced, or used in any delivery execution path.

---

## Scope

### In Scope
- Quote → Ticket creation
- Removal of Task-based execution
- Projection and event alignment
- Seed/demo alignment
- Terminology correction across system

### Out of Scope
- CRM redesign
- UI redesign
- Finance changes

---

## Authoritative Decisions

### Tasks Removal
- No Task creation anywhere in delivery flow
- All Task-based logic removed or bypassed
- Any remaining Task code must be unreachable

---

### Terminology Correction (MANDATORY)
All references to “Task” meaning execution work must be replaced with “Ticket” across:
- code
- documentation
- seed/demo data
- projections/events

No mixed terminology allowed.

---

### Ticket Creation Rules

Each Ticket MUST include:
- project_id
- customer_id
- source_type = 'quote_component'
- source_component_id
- lifecycle_owner = 'project'
- primary_container = 'project'
- billing_context_type = 'project'
- sold_minutes (immutable)
- is_rollup
- parent_ticket_id

---

### Component Mapping
- Simple / once-off → 1 Ticket
- Implementation:
  - single unit → 1 Ticket
  - multiple units → 1 rollup + child tickets

---

### Idempotency (STRICT)
- Add source_component_id column
- UNIQUE(project_id, source_component_id, parent_ticket_id)
- No duplicate tickets allowed

---

### SLA Isolation
Only lifecycle_owner = 'support' is processed by SLA.

---

### Feed Behaviour
Ticket creation during quote acceptance is silent.
Only QuoteAccepted is shown.

---

### Time Rules
- Rollup → no time
- Leaf → allow time

---

## Seed / Demo Requirements

Seed system must:
- create Tickets only (no Tasks)
- remain idempotent
- maintain or improve demo health (GREEN)
- align diagnostics with Ticket model

---

## Acceptance Criteria
- No Task records created
- Tickets represent all delivery work
- No duplicates
- Seed remains GREEN
- Terminology fully consistent
- Time tracking correct
- SLA unaffected

---

## Tests
- ticket_creation_on_quote_accept
- idempotency
- concurrency safety
- sla_exclusion
- no_task_creation
- seed_integrity_preserved
