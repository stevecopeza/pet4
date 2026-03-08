# PET – Invariants and Safety Rules

## Purpose of this Document
This document defines **hard invariants** enforced by PET.

Violations are handled via **hard errors by default**. The system blocks the action, requires resolution, and only then allows continuation.

Warnings are insufficient where trust, auditability, or financial correctness are at risk.

---

## Global Enforcement Policy

- Invariants are enforced at the **domain layer**, not UI
- Administrators are subject to the same invariants as all users
- Overrides are not permitted unless explicitly defined here
- All violations are surfaced immediately and explicitly

---

## Data Integrity Invariants

### I‑01: No Silent Data Mutation

- Historical facts must not be overwritten
- Corrections create new records linked to the original
- Original records remain visible

**Violation handling:** Block action

---

### I‑02: Forward‑Only Schema Evolution

- Malleable field schemas apply forward only
- Existing records retain original structure
- Schema version is recorded per record

**Violation handling:** Block schema change

---

### I‑03: No Hard Deletion of Core Records

Core records include:
- Customers
- Projects
- Quotes
- Time entries
- Tickets

These may be archived but never hard‑deleted.

**Violation handling:** Block deletion

---

## Commercial Invariants

### I‑04: Quote Immutability After Signature

- Signed quotes are immutable
- Any change requires a delta or cloned quote
- Original quote remains authoritative

**Violation handling:** Block edit

---

### I‑05: Sales Obligation Lock‑In

- Accepted quotes lock time and cost expectations
- Delivery work must reconcile against sold constraints

**Violation handling:** Block delivery plan save

---

## Time and Resource Invariants

### I‑06: All Time Must Have Context

- Time entries require task context
- Non‑project time must still map to an operational bucket

**Violation handling:** Block time submission

---

### I‑07: Time Entries Are Append‑Only

- Submitted time entries cannot be edited
- Corrections require compensating entries

**Violation handling:** Block edit

---

### I‑08: Sold Time Caps Are Enforced

- Project planning may not exceed sold time
- Variance requires explicit acknowledgement and tracking

**Violation handling:** Block save until variance resolved

---

## KPI Invariants

### I‑09: KPIs Are Derived Only

- No manual KPI entry is allowed
- KPI calculations are transparent and reproducible

**Violation handling:** Block KPI modification

---

### I‑10: KPI Source Events Are Immutable

- Events feeding KPIs cannot be edited or deleted

**Violation handling:** Block action

---

## Support and SLA Invariants

### I‑11: All Support Work Resolves to a Ticket

- Support time must be associated with a ticket
- SLA applicability must be explicit

**Violation handling:** Block time submission

---

### I‑12: SLA Breaches Are Facts

- SLA breaches are system‑recorded events
- Breaches cannot be dismissed or hidden

**Violation handling:** Block status override

---

## Security and Permissions Invariants

### I‑13: Role and Team Based Access

- Permissions derive from role, team, and org position
- Ad‑hoc per‑object overrides are restricted

**Violation handling:** Block permission grant

---

### I‑14: Self‑Service Boundaries

- Users may update limited personal data only
- Sensitive fields (pay, role history) are restricted

**Violation handling:** Block update

---

## Activity and Audit Invariants

### I‑15: Activity Feed Accuracy

- Activity feed reflects factual events only
- Manual edits are not permitted

**Violation handling:** Block activity mutation

---

### I‑16: Resolution Before Continuation

- When an invariant is violated, the system halts progress
- Resolution must occur before workflow continues

This ensures errors are dealt with immediately.

---

## Exception Policy

Exceptions are:
- Explicit
- Documented
- Rare

Any exception must be added to this document before implementation.

---

**Authority**: Normative

This document defines PET’s safety envelope. It is not optional.