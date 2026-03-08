# PET – Time and Resource Accounting

## Purpose of this Document
This document defines how **time and resources are recorded, corrected, reconciled, and measured** in PET.

Time is the backbone of PET. If time accounting is wrong, every KPI, quote, SLA, and invoice becomes unreliable.

---

## Core Principle

**All work performed must be represented as time, and all time must be attributable.**

Time is factual. Interpretation comes later.

---

## Time as a First‑Class Domain Object

Time is not:
- A billing afterthought
- A UI convenience
- A spreadsheet export

Time is:
- A primary domain signal
- The basis of delivery truth
- The reconciliation layer between sales, delivery, and support

---

## Time Entry Definition

A **Time Entry** represents a factual record of work performed.

Required attributes:
- Employee
- Start / end (or duration)
- Task
- Date
- Classification (billable / non‑billable)
- Work type (project / support / admin)

Time entries without task context are invalid.

---

## Task Anchoring Rule

Every Time Entry must be anchored to a **Task**.

Rules:
- Project tasks are preferred
- Support tasks must reference a Ticket
- Administrative tasks exist as explicit operational buckets

Free‑floating time is not permitted.

---

## Draft and Submission Lifecycle

Time Entry lifecycle:

```
Draft → Submitted → Locked
```

Rules:
- Draft entries may be edited by the owner
- Submission represents attestation of truth
- Locked entries are immutable

---

## Correction Model (Compensating Entries)

Errors in time logging are corrected via **compensating entries**.

Rules:
- Original entry is preserved
- Correction is a new entry
- Correction references the original

Examples:
- Negative duration entry
- Reclassification entry
- Re‑attribution entry

Edits to submitted or locked entries are forbidden.

---

## Billable vs Non‑Billable

Classification is explicit at entry time.

Rules:
- Billable status affects invoicing eligibility
- Non‑billable time still feeds KPIs

Reclassification requires compensating entries.

---

## Sold Time Reconciliation

For sold work:

- Each Quote defines an expected time budget
- Projects inherit sold time constraints
- Time entries consume sold capacity

Rules:
- Overruns are visible immediately
- Underruns are measurable outcomes

---

## Support and SLA Time

Support time:
- Must reference a Ticket
- May or may not be billable
- Feeds SLA compliance metrics

Time logged outside tickets cannot be considered support.

---

## Resource Availability

PET tracks:
- Planned allocation
- Actual time spent
- Remaining capacity

This enables:
- Resource levelling
- Forecasting
- Early overload detection

---

## Invoicing Interface

Time entries marked billable:
- Are eligible for invoicing
- Are grouped by customer, project, and period
- Are exported as invoice intent

Accounting systems confirm execution; PET remains authoritative on effort.

---

## KPI Integration

Time feeds KPIs such as:
- Utilisation
- Delivery efficiency
- SLA performance
- Estimate accuracy

All KPI derivations reference immutable time events.

---

## What This Prevents

- Silent time edits
- Lost effort
- Billing disputes
- KPI distortion

---

## See also: Ticket Backbone

The Ticket Backbone enforcement rules ensure that time entries are anchored to Tickets (not just tasks) and that ticket_id is present at submission/lock boundaries while preserving immutability.

Related documents:

- `00_foundations/01_Ticket_Backbone_Principles_and_Invariants_v1.md`
- `04_time/05_Time_Entry_Ticket_Enforcement_v1.md`
- `05_data_model/02_Ticket_Data_Model_and_Migrations_v1.md`

---

**Authority**: Normative

This document defines how PET accounts for time and resources.
