# PET – Domain Language

## Purpose of this Document
This document defines the **canonical meaning** of core PET terms.

These definitions are **authoritative**. UI labels, database structures, APIs, and reports must conform to this language.

If a term is ambiguous in conversation, this document resolves the ambiguity.

---

## Foundational Concepts

### Event
An **Event** is an immutable record that something happened at a specific time.

Examples:
- Time logged
- Quote signed
- Ticket status changed
- SLA breached

Events are append‑only and form the basis of all KPIs.

---

### Context
**Context** is the minimum set of references required to understand an event.

Context may include:
- Customer
- Site
- Contact
- Project
- Task
- Ticket

An event without sufficient context is considered invalid.

---

## Commercial Flow

### Lead
A **Lead** represents unqualified potential business.

Characteristics:
- May be incomplete or messy
- May lack a customer, site, or contact
- Exists to capture possibility, not commitment

Leads are expected to be noisy.

---

### Qualification
**Qualification** is the structured process of determining whether a lead is worth pursuing.

Qualification:
- Introduces required fields and constraints
- Establishes minimum understanding of the customer environment
- Determines opportunity class (Gold / Silver / Bronze)

Qualification consumes time and resources and is therefore measurable.

---

### Opportunity
An **Opportunity** represents a qualified commercial intent.

Characteristics:
- Has defined scope boundaries (even if rough)
- Has allocated pre‑sales resources
- Is tracked for conversion probability and effort

Opportunities are classified (Gold / Silver / Bronze) to control investment depth.

---

### Quote
A **Quote** is a formal commercial offer.

Properties:
- Versioned
- Time‑reconcilable
- Financially explicit

Rules:
- Unsigned quotes may evolve
- Signed quotes are immutable
- Changes require delta or cloned quotes

A signed quote represents a **binding obligation**.

---

### Sale
A **Sale** occurs when a quote is accepted.

A sale:
- Transitions commercial intent into delivery obligation
- Locks financial and time expectations
- Becomes the parent of delivery work

---

## Delivery and Execution

### Project
A **Project** is a structured delivery effort.

Properties:
- May originate from a sale or be created manually
- Has a finite or semi‑finite lifecycle
- Contains milestones and tasks

Projects are accountable to sold constraints.

---

### Milestone
A **Milestone** is a grouping of work with commercial significance.

Properties:
- Aggregates tasks
- Has cumulative cost and time expectations
- Cannot exceed sold totals without explicit variance

---

### Task
A **Task** is the smallest unit of planned work.

Rules:
- All time must be logged against a task
- Tasks belong to a project or operational bucket
- Tasks may evolve, but original commitments remain visible

---

### Time Entry
A **Time Entry** is a factual record of time spent.

Properties:
- Immutable once submitted
- Attributed to a person, task, and time window
- Classified (billable / non‑billable / support / admin)

Time entries are primary KPI inputs.

---

## Support and Knowledge

### Ticket
A **Ticket** represents a request for help or intervention.

Rules:
- All support work resolves to a ticket
- Tickets may be linked to projects or stand alone
- SLA applicability is explicit

Tickets are both operational and analytical objects.

---

### SLA
A **Service Level Agreement** defines expected response and resolution behaviour.

Properties:
- Time‑bound
- Enforceable
- Measurable

SLA breaches are events, not opinions.

---

### Knowledge Article
A **Knowledge Article** is a curated explanation of how or why something is done.

Properties:
- Versioned
- Authored
- Commentable
- Context‑linkable

Knowledge exists to reduce future work.

---

## Organisation and People

### Employee
An **Employee** is a person performing work within PET.

Properties:
- Has roles, teams, and history
- Generates time entries, tickets, and events
- Is subject to KPIs derived from activity

---

### Team
A **Team** is a logical grouping of employees.

Properties:
- Employees may belong to multiple teams
- Teams may have managers
- Teams are used for visibility and aggregation

---

## Measurement

### KPI
A **KPI** is a derived indicator calculated from events.

Rules:
- Never manually entered
- Transparent in derivation
- Interpreted via thresholds and targets

KPIs describe reality; they do not define it.

---

### Activity Feed
The **Activity Feed** is a chronological view of events.

Characteristics:
- Factually accurate
- Context‑rich
- Part audit trail, part operational awareness

The feed is a window into system truth.

---

**Authority**: Normative

This document defines PET language. Divergence is not permitted.

