# PET – Project Delivery

## Purpose of this Document
This document defines how PET delivers work **after a sale**, ensuring that execution remains aligned with what was sold.

Project Delivery is where commercial promises meet operational reality. PET enforces this junction explicitly.

---

## Scope

This document covers:
- Project creation
- Planning under sold constraints
- Milestones and tasks
- Execution tracking
- Variance handling
- Completion and closure

It does **not** define:
- Quoting mechanics (see `quoting_and_pricing.md`)
- Time entry mechanics (see `time_and_resource_accounting.md`)
- Support workflows (see `helpdesk_and_sla.md`)

---

## Core Principles Applied

- Projects respect sold time and cost constraints
- Planning may evolve, sold totals may not
- Variance is visible and explicit
- Delivery truth is derived from time entries

---

## Project Creation

### From a Sale (Primary Path)

When a Quote is Accepted:
- A Sale is created
- One or more Projects become eligible for creation
- Sold time budgets are attached to the Project

Rules:
- Projects inherit customer, site, and commercial context
- Sold hours are immutable reference values

---

### Manual Project Creation

Projects may be created manually for:
- Internal initiatives
- Pre‑sales work
- Retrospective structuring

Rules:
- Must declare whether sold constraints apply
- Internal projects still track time and KPIs

---

## Planning Model

### Milestones

Milestones:
- Represent commercially meaningful checkpoints
- Aggregate Tasks
- Carry cumulative planned hours

Rules:
- Sum of milestone hours must not exceed sold hours
- Adjustments require variance acknowledgement

---

### Tasks

Tasks:
- Are the unit of execution
- Are assigned to roles or individuals
- Anchor all time entries

Rules:
- Tasks may be added, removed, or re‑sequenced
- Aggregate hours must respect milestone limits

---

## Planning Flexibility vs Constraints

PMs may:
- Refine task breakdown
- Reassign resources
- Adjust sequencing

PMs may not:
- Increase total sold hours
- Hide overruns

Any deviation beyond sold constraints creates a **variance state**.

---

## Execution Tracking

### Status Tracking

Projects track:
- Planned vs actual hours
- Milestone completion
- Task progress

Status derives from:
- Task state
- Time entries

Manual status overrides are not permitted.

---

## Variance Handling

When planned or actual work exceeds sold constraints:

Rules:
- Variance is flagged immediately
- Cause must be recorded
- Resolution requires explicit action (e.g. change request)

Variance is not an error; hiding it is.

---

## Change Requests

Out‑of‑scope work is handled via:
- New Quote
- Delta Quote

Rules:
- Original project remains intact
- Additional work becomes a new obligation

---

## Resource Visibility

Project schedules feed:
- Universal calendar
- Resource availability views

Delivery competes fairly with sales and support for time.

---

## Completion and Closure

A Project may be completed when:
- All tasks are completed or explicitly cancelled
- Variance is resolved or acknowledged

Completed projects:
- Become immutable
- Remain visible for reporting

---

## Measurement and KPIs

Project KPIs include:
- Estimate accuracy
- Delivery efficiency
- Variance frequency
- Time to completion

KPIs derive from immutable events and time entries.

---

## Hard Blocks and Errors

The following actions are blocked:
- Logging time against a completed project
- Increasing sold hour totals
- Completing a project with unresolved variance

---

## What This Prevents

- Scope creep without accountability
- Delivery rewriting sales history
- Invisible overruns

---

## Technical Implementation

- **API**: `/pet/v1/projects` (GET, POST)
- **API**: `/pet/v1/projects/{id}` (PUT, DELETE/Archive)
- **Frontend**: `Projects.tsx`, `ProjectForm.tsx` (Unified Add/Edit component).
- **Malleable Fields**: Supported (Schema: `project`).

---

**Authority**: Normative

This document defines how PET enforces project delivery against sold commitments.

