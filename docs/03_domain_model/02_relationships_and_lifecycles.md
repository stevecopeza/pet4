# PET – Domain Model: Relationships and Lifecycles

## Purpose of this Document
This document defines **how PET domain entities relate to one another** and **how they move through their lifecycles**.

It establishes:
- Mandatory vs optional relationships
- Creation and termination rules
- Visibility and immutability guarantees

No workflows or UI behaviour are defined here — only structural truth.

---

## Global Lifecycle Rules

### Visibility
- All entities remain **visible forever** unless explicitly archived
- Archival removes entities from default operational views, not history

### Immutability at Terminal States
- Once an entity reaches a terminal state, it becomes **immutable**
- Re‑opening is not permitted; new entities must be created instead

This preserves analytical and legal integrity.

---

## Identity Relationships

### Customer

- A Customer may have **many Sites**
- A Customer may have **many Contacts**
- A Customer anchors:
  - Opportunities
  - Quotes
  - Sales
  - Projects
  - Tickets

A Customer cannot be deleted if any dependent entities exist.

---

### Site

- A Site belongs to **exactly one Customer**
- A Site may have **many Contacts**
- A Site may anchor Projects and Tickets

Sites inherit commercial context from their Customer.

---

### Contact

- A Contact may belong to **multiple Customers**
- A Contact may belong to **multiple Sites**
- A Contact may be associated with:
  - Leads
  - Opportunities
  - Tickets

Contacts retain identity even if relationships change.

---

## Commercial Lifecycle

### Lead

Lifecycle:

```
new → qualified → converted → archived
              ↓
         disqualified → archived
```

Rules:
- Leads require a Customer (`customerId`)
- Leads are mutable until converted or disqualified
- Conversion creates a linked Quote (`lead_id` FK on quote) and sets `convertedAt`
- A Lead may convert directly to a Quote (Qualification/Opportunity steps are planned but not yet implemented)

Relationships:
- Lead → Customer (required, many-to-one)
- Lead → Quote (optional, via `lead_id` on quote; set on conversion)

---

### Qualification
**STATUS: NOT YET IMPLEMENTED — Planned**

Lifecycle:

```
Started → Completed → Archived
```

Rules:
- Qualification consumes measurable effort
- Completion gates Opportunity creation

---

### Opportunity
**STATUS: NOT YET IMPLEMENTED — Planned**

Lifecycle:

```
Created → Active → Quoted → Won → Archived
                     ↓
                   Lost → Archived
```

Rules:
- Opportunities require a Customer
- Gold / Silver / Bronze classification affects resource allocation

Note: Currently, Leads convert directly to Quotes. This entity will be introduced when pipeline complexity warrants it.

---

### Quote

Lifecycle:

```
draft → sent → accepted
             ↓
          rejected
```

Terminal states: accepted, rejected, archived

Rules:
- Draft and Sent states are mutable
- Accepted quotes are immutable
- Changes require new Quote entities (versioning)
- May originate from a Lead (`lead_id`, nullable) or be created directly for a Customer

Relationships:
- Quote → Customer (required, many-to-one)
- Quote → Lead (optional, many-to-one via `lead_id`)
- Quote → Components (one-to-many: Implementation, Catalog, OnceOffService, RecurringService)
- Quote → PaymentSchedule (one-to-many)
- Quote → CostAdjustments (one-to-many)

Acceptance triggers (via QuoteAccepted event):
- Contract creation (with SLA snapshot)
- Baseline v1 creation
- Project creation (from ImplementationComponent)
- Forecast creation (committed, probability=1.0)
- Ticket creation: one ticket per sold labour item with immutable `sold_minutes` and `is_baseline_locked = 1` (see `00_foundations/02_Ticket_Architecture_Decisions_v1.md`)
- Feed event projection

---

### Sale
**STATUS: NOT YET IMPLEMENTED — Planned**

Lifecycle:

```
Created → Fulfilled → Archived
```

Rules:
- Sale creation is triggered by quote acceptance
- Sale is immutable once created

Note: Currently the QuoteAccepted event chain handles sale-equivalent behaviour. A dedicated Sale entity will be introduced if explicit sale tracking is needed beyond the Contract.

---

## Delivery Lifecycle

### Project

Lifecycle:

```
Created → Planned → Active → Completed → Archived
                         ↓
                       Cancelled → Archived
```

Rules:
- Projects inherit constraints from Sales where applicable
- Completed projects are immutable

---

### Milestone

Lifecycle:

```
Defined → Active → Completed → Archived
```

Rules:
- Milestones aggregate Tasks
- Completion locks cumulative values

---

### Task

Lifecycle:

```
Planned → In Progress → Completed → Archived
```

Rules:
- Tasks must belong to a Project or operational bucket
- Completed tasks are immutable

---

## Time and Resource Lifecycle

### Time Entry

Lifecycle:

```
Draft → Submitted → Locked
```

Rules:
- Draft time may be edited by the creating employee only
- Submitted and locked time is immutable
- Corrections to submitted/locked entries use compensating entries (reversal + corrected re-log via `corrects_entry_id`)
- `ticketId` and `employeeId` are immutable from creation

---

## Support / Ticket Lifecycle

### Ticket

Tickets have context-specific lifecycles governed by `lifecycle_owner`:

Support:
```
new → open → pending → resolved → closed
```

Project:
```
planned → ready → in_progress → blocked → done → closed
```

Internal:
```
planned → in_progress → done → closed
```

Rules:
- All work (support, project, internal) maps to a Ticket
- Transitions enforced by `TicketStatus` value object per lifecycle context
- Terminal state (`closed`) is immutable
- Roll-up tickets aggregate from children; leaf tickets accept time entries
- Assignment (`queueId` + `ownerUserId`) is independent of who may log work

---

### SLA

Lifecycle:

```
Defined → Active → Expired → Archived
```

Rules:
- SLA breaches are recorded as events
- SLAs do not retroactively apply

---

## Knowledge Lifecycle

### Knowledge Article

Lifecycle:

```
Draft → Published → Revised → Archived
```

Rules:
- Revisions create new versions
- Historical versions remain accessible

---

## What This Enables

- Deterministic KPIs
- Safe reporting across years
- Legal and contractual defensibility

---

**Authority**: Normative

This document defines how PET entities relate and evolve. Violations are not permitted.

