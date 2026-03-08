# PET – Quoting and Pricing

## Purpose of this Document
This document defines how PET constructs, versions, approves, accepts, and enforces **quotes as binding commercial artifacts**.

Quoting in PET is not a pricing exercise alone; it is the point where **intent becomes obligation**.

This document must comply with all previously defined foundations, architecture, domain rules, and invariants.

---

## Scope

This document covers:
- Quote composition
- Versioning and immutability
- Pricing models (once‑off, project, recurring)
- Time reconciliation
- Customer acceptance
- Payment plans

It does **not** define invoicing execution (accounting domain) or project delivery mechanics (delivery domain).

---

## Core Principles Applied

- Signed quotes are immutable
- All pricing reconciles to time
- Changes are represented as deltas, not edits
- Quotes are explainable, auditable, and measurable

---

## Quote Composition Model

A Quote is composed of **line groups**, each representing a commercial intent.

### Line Group Types

1. **Product**
   - Software licenses, hardware items
   - Sourced from `CatalogProduct` (products-only catalog)
   - Pricing: `quantity × unit_price` (snapshotted from catalog at line creation)

2. **Project / Implementation (Labour)**
   - Structured work delivered over time
   - Sourced from Role + ServiceType + RateCard resolution
   - Pricing: `hours × sell_rate` (snapshotted from RateCard); cost = `hours × base_internal_rate` (snapshotted from Role)

3. **Service / Support (Labour)**
   - SLA‑backed or ad‑hoc services
   - Same economics as Project labour (Role + RateCard)

Each group has independent pricing logic but contributes to a single obligation.

> **Authoritative pricing model:** See `03_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md` for full entity specifications, rate card resolution algorithm, and snapshot rules.

---

## Pricing Models

### Once‑Off Charges

Used for:
- Hardware
- Setup fees
- One‑time services

Rules:
- Fixed price
- No time overrun protection unless explicitly defined

---

### Project Pricing

Project pricing is **time‑reconcilable**.

Structure:
- Milestones
- Tasks
- Resource roles

Rules:
- Each task has estimated hours
- Tasks roll up to milestones
- Milestones roll up to total project hours

Total project cost is derived from time × rate.

---

### Recurring Charges

Used for:
- Support retainers
- Subscription services

Rules:
- Defined recurrence (monthly, quarterly, annually)
- Defined expected effort per period
- Time reconciliation applies per period

---

## Time Reconciliation

Every non‑product line item must map to:
- Expected hours
- Resource role

Rules:
- Total quoted hours become the **sold time budget**
- This budget is inherited by delivery projects
- Overruns are detected immediately during delivery

---

## Versioning Model

### Quote States

```
Draft → Sent → Accepted → Locked → Archived
```

Rules:
- Draft and Sent quotes may be edited
- Accepted quotes transition to Locked
- Locked quotes are immutable

---

### Quote Changes

Changes to a Locked quote require:

- A **delta quote** (additive or corrective), or
- A **cloned quote** with explicit supersession

Rules:
- Original quote remains authoritative
- Delta quotes reference the parent
- Financial totals are aggregated explicitly

---

## Customer Acceptance

Acceptance:
- Occurs online
- Is timestamped
- Is attributable to a Contact

Acceptance produces a **QuoteAccepted event** and triggers:
- Sale creation
- Project setup eligibility

---

## Attachments and Supporting Content

Quotes may include:
- Documents
- Product brochures
- Guides

Rules:
- Attachments are versioned
- Attachments are immutable once sent

---

## Engagement Tracking

Customer engagement with quotes is tracked via events:
- Sent
- Viewed
- Accepted
- Rejected

Communication channels (email, WhatsApp, manual notes) are linked but do not mutate quote state.

---

## Payment Plans

Quotes may include a **payment plan summary**.

Supported models:
- Deposit + balance
- Milestone‑based payments
- Equal recurring payments
- Progress‑based billing

Rules:
- Payment plans describe intent
- Execution is handled by accounting systems
- Deviations are reconciled, not overwritten

---

## Measurement and KPIs

Quote‑level KPIs include:
- Win / loss rate
- Time to acceptance
- Estimate accuracy (post‑delivery)

KPIs derive from quote and delivery events.

---

## Hard Blocks and Errors

The following actions are blocked:
- Editing a Locked quote
- Removing time reconciliation from project lines
- Accepting a quote without required context

Resolution is required before continuation.

---

## What This Prevents

- Silent scope creep
- Re‑negotiated history
- Unmeasured delivery risk
- Billing disputes

---

**Authority**: Normative

This document defines how PET handles quoting and pricing.

