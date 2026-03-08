# PET – Data Ownership and Boundaries

## Purpose of this Document
This document defines **who owns what data**, where authority lies, and how conflicts are resolved between PET and external systems.

This is critical to prevent silent corruption of operational truth.

---

## Core Principle

**PET is the authoritative source for operational reality.**

External systems may be authoritative within their own domain, but never override PET’s facts.

---

## Ownership by Domain

### PET Owns (Authoritative)

PET is the system of record for:

- Leads, opportunities, and qualification data
- Quotes and quote versions
- Sales commitments
- Projects, milestones, and tasks
- Time entries and resource allocation
- Tickets, SLAs, and support activity
- KPI source events and derived metrics
- Activity and audit trails

This data must never be overwritten by external input.

---

### External Systems Own (Authoritative)

External systems may be authoritative for:

- Accounting ledgers (e.g. QuickBooks)
- Tax calculations
- Payment processing confirmation

PET does not attempt to replicate or replace these concerns.

---

## Shared Concepts (Boundary Objects)

Some concepts exist in both PET and external systems.

Examples:
- Invoices
- Customers (accounting vs operational view)
- Payments

Rules:
- PET owns the **operational intent**
- External systems own the **financial execution**

---

## Conflict Resolution Strategy

When discrepancies arise:

1. PET data is preserved
2. External data is imported as a **reconciliation record**
3. Differences are surfaced explicitly
4. Human resolution is required where needed

No automatic overwrite is permitted.

---

## QuickBooks Integration Boundary

### PET Sends

- Invoice intents
- Line items with time and project context
- Customer identifiers

### PET Receives

- Invoice status
- Payment confirmations
- Rejection or adjustment notices

PET records these as **events**, not mutations.

---

## Communication Channels (Email, WhatsApp)

External communications:

- Are captured as interaction records
- Do not alter domain state directly
- May trigger workflows via explicit actions

Message receipt ≠ state change.

---

## KPI Protection

- KPIs derive only from PET events
- External metrics are informational only
- No KPI input may originate externally

---

## Failure Modes

External failure scenarios:

- API downtime
- Partial sync
- Data mismatch

Rules:
- PET remains consistent
- Failures are logged as events
- Retries are explicit and traceable

---

## What This Prevents

- Accounting systems rewriting operational history
- Support tools silently closing tickets
- Manual reconciliation outside the system

---

**Authority**: Normative

This document defines PET’s data sovereignty.

