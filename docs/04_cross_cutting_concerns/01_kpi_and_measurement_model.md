# PET – KPI and Measurement Model

## Purpose of this Document
This document defines how PET measures reality.

It specifies:
- What can be measured
- How KPIs are defined
- How KPIs evolve safely
- How trust in numbers is preserved

KPIs are not reports. They are **derived truths**.

---

## Core Measurement Principle

**All KPIs are derived from immutable events.**

There is no manual KPI entry, adjustment, or override.

If a KPI looks wrong, the resolution is to fix inputs or definitions — not the output.

---

## Measurement Building Blocks

### Event

An Event is the atomic unit of measurement.

Properties:
- Immutable
- Timestamped
- Attributed to an actor
- Context‑rich (customer, project, task, ticket, etc.)

Examples:
- TimeEntrySubmitted
- QuoteAccepted
- TicketResolved
- SLABreached

---

### Metric

A Metric is a **raw quantitative extraction** from events.

Examples:
- Total hours logged
- Mean time to resolution
- Number of SLA breaches

Metrics do not imply judgement.

---

### KPI

A KPI is a **judgemental construct** derived from one or more metrics.

Properties:
- Declarative definition
- Versioned
- Context‑scoped

KPIs express performance, not activity.

---

## Declarative KPI Definitions

KPIs are defined using declarative rules that specify:

- Source events
- Filters (time window, team, project, customer)
- Aggregations (sum, average, percentile)
- Thresholds and targets
- Classification logic (good / warning / breach)

Definitions are data, not code.

---

## KPI Versioning

Rules:
- KPI definitions are versioned
- Changes create a new version
- Historical KPI values remain linked to the version used at the time

This prevents reinterpretation of history.

---

## Managerial Influence

Managers may:
- Adjust targets
- Change thresholds
- Re‑weight contributing metrics

Managers may not:
- Alter events
- Override computed values
- Rewrite historical outcomes

---

## KPI Scope

KPIs may be scoped to:

- Individual (employee)
- Team
- Department
- Customer
- Project
- Organisation

Scope is explicit in the definition.

---

## Recalculation Strategy

- KPIs are recalculated deterministically
- Recalculation may be triggered by:
  - New events
  - Definition changes
- Recalculation is idempotent

---

## Trust and Explainability

Every KPI must be explainable.

PET must be able to show:
- Which events contributed
- Which definition version was used
- Why a classification was reached

If a KPI cannot be explained, it is invalid.

---

## What This Prevents

- KPI gaming
- Spreadsheet reconciliation
- Silent metric drift
- Management by anecdote

---

**Authority**: Normative

This document defines how PET measures performance.

