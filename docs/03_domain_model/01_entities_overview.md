# PET – Domain Model: Entities Overview

## Purpose of this Document
This document defines the **authoritative set of domain entities** in PET and their high‑level responsibilities.

It does not define workflows or state transitions (those are covered later). It answers one question only:

> **What exists in the PET domain, and why?**

All entities listed here are part of a **single unified domain model**, organised into bounded contexts for clarity — not isolation.

---

## Structural Rules

- Every entity has a stable identity
- Every entity has a lifecycle
- Every entity emits events
- No entity exists without purpose

Entities are not UI constructs and are not equivalent to WordPress concepts.

---

## Core Identity Context

### Customer
Represents a business PET has or may have a commercial relationship with.

Responsibilities:
- Acts as the primary commercial counterparty
- Anchors sales, delivery, support, and finance context

Notes:
- Never hard‑deleted
- May be archived

---

### Site
Represents a physical or logical location belonging to a Customer.

Responsibilities:
- Provides location‑specific context
- Supports multi‑site customers

Notes:
- Always belongs to exactly one Customer

---

### Contact
Represents a person external to the organisation.

Responsibilities:
- Acts as a communication endpoint
- May be linked to multiple Customers and Sites

Notes:
- Identity is stable even if affiliations change

---

## Organisation Context

### Employee
Represents a person performing work within PET.

Responsibilities:
- Generates time entries
- Owns actions and events
- Is subject to KPIs

Notes:
- Separate from WordPress user lifecycle

---

### Team
Represents a logical grouping of Employees.

Responsibilities:
- Visibility and aggregation
- Managerial oversight

Notes:
- Employees may belong to multiple teams

---

## Commercial Context

### Lead
Represents unqualified potential business.

Responsibilities:
- Capture inbound or discovered opportunity
- Hold incomplete or messy data
- May convert into a Quote via explicit conversion action

Notes:
- Requires a Customer
- Carries optional `estimatedValue` and `source`
- Supports malleable data fields
- May link to a Quote via `lead_id` on the Quote (nullable — quotes may also be created directly)

---

### Qualification
**STATUS: NOT YET IMPLEMENTED — Planned**

Represents structured assessment of a Lead.

Responsibilities:
- Establish minimum understanding
- Gate progression to Opportunity

---

### Opportunity
**STATUS: NOT YET IMPLEMENTED — Planned**

Represents qualified commercial intent.

Responsibilities:
- Track pre‑sales investment
- Drive quote creation

Notes:
- Currently, Leads convert directly to Quotes. The Opportunity entity will be introduced when pipeline complexity warrants it.

---

### Quote
Represents a formal commercial offer.

Responsibilities:
- Define scope, cost, and time expectations
- Act as a binding artifact once accepted
- Carry component-based structure (Implementation, Catalog, OnceOffService, RecurringService)

Notes:
- Versioned
- Immutable once accepted
- May originate from a Lead (`lead_id`) or be created directly for a Customer
- Contains payment schedule, cost adjustments, and malleable data
- Acceptance triggers: Contract, Baseline, Project, Forecast, execution Tickets

---

### Sale
**STATUS: NOT YET IMPLEMENTED — Planned**

Represents acceptance of a Quote.

Responsibilities:
- Transition intent into obligation
- Trigger delivery setup

Notes:
- Currently the QuoteAccepted event chain handles sale-equivalent behaviour (Contract + Baseline + Project creation). A dedicated Sale entity will be introduced if explicit sale tracking is needed beyond the Contract.

---

## Delivery Context

### Project
Represents structured delivery work.

Responsibilities:
- Enforce sold constraints
- Track progress vs plan

---

### Milestone
Represents a commercially meaningful grouping of work.

Responsibilities:
- Aggregate tasks
- Provide progress checkpoints

---

### Task
Represents a unit of planned work.

Responsibilities:
- Anchor time tracking
- Enable execution detail

---

## Time and Resource Context

### Time Entry
Represents recorded time spent by an Employee.

Responsibilities:
- Provide factual record of effort
- Feed KPIs and billing
- Support corrections via compensating entries (`corrects_entry_id`)

Notes:
- Append‑only (submitted/locked entries are immutable; corrections create new entries)
- Lifecycle: draft → submitted → locked
- `createCorrection()` / `createReversal()` factory methods for compensating entries

---

### WorkItem
Represents a projected, queueable unit of work derived from a Ticket or other source.

Responsibilities:
- Feed the operational queue and priority scoring
- Mirror assignment, SLA state, and commercial context from source entity

Notes:
- Source types: `ticket`, `project_task`, `escalation`, `admin`
- Statuses: `active`, `waiting`, `completed`
- Projected (not source-of-truth) — rebuilt from domain events

---

### Contract
Represents an accepted commercial agreement derived from a Quote.

Responsibilities:
- Track commercial obligation lifecycle
- Gate delivery against sold scope

Notes:
- Created automatically on QuoteAccepted event
- Lifecycle: active → completed | terminated
- Immutable once in terminal state

---

### Baseline
Represents the immutable "what was sold" snapshot at contract formation.

Responsibilities:
- Preserve sold WBS, internal cost ceiling, and margin at point of sale
- Serve as reference for variance/change control

Notes:
- Created automatically on QuoteAccepted event alongside Contract
- Re-baseline only via explicit action; historical baselines preserved

---

### Forecast
Represents projected revenue derived from a Quote.

Responsibilities:
- Track expected revenue with probability weighting
- Distinguish pending vs committed forecasts

Notes:
- Created automatically on QuoteAccepted event (committed, probability=1.0)
- Supports breakdown by component type

---

## Support Context

### Ticket
Represents the **universal work unit** across support, project, and internal contexts.

Responsibilities:
- Capture any trackable work demand (support incident, project deliverable, internal task)
- Enforce SLA where applicable (support context)
- Carry commercial context (`billing_context_type`, `agreement_id`, `is_billable_default`)
- Support WBS hierarchy (`parent_ticket_id`, `root_ticket_id`, `is_rollup`)
- Govern lifecycle transitions per `lifecycle_owner` (support/project/internal)

Notes:
- Single table `wp_pet_tickets` with `primary_container` ENUM('support','project','internal')
- Lifecycle state machine enforced by `TicketStatus` value object
- Assignment: `queueId` (team) + `ownerUserId` (individual) with domain events
- Roll-up tickets (`is_rollup=1`) may not accept time entries

---

### SLA
Represents a service commitment.

Responsibilities:
- Define response and resolution expectations
- Generate breach events

Notes:
- SLA state evaluation is a pure domain function (`SlaStateResolver`) — determines ACTIVE, WARNING, BREACHED, or PAUSED
- SLA orchestration (check scheduling, persistence) is application-layer (`SlaCheckService`)
- `SlaClockState` entity tracks escalation stage, pause state, and last dispatched event per ticket

---

## Knowledge Context

### Knowledge Article
Represents curated operational knowledge.

Responsibilities:
- Capture solutions and learnings
- Reduce future effort

---

## Measurement Context

### Event
Represents an immutable fact.

Responsibilities:
- Feed KPIs
- Populate activity feed

---

### KPI
Represents a derived indicator.

Responsibilities:
- Describe performance
- Support decision‑making

---

## Cross‑Cutting Context

### Activity Feed Item
Represents a rendered view of events.

Responsibilities:
- Provide situational awareness
- Preserve auditability

---

## What This Document Deliberately Avoids

- Workflow definitions
- State transitions
- UI behaviour
- Database schemas

Those are defined in subsequent documents.

---

**Authority**: Normative

This document defines what exists in PET. Entities not listed here do not exist.

