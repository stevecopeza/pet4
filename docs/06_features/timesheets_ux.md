# PET – Timesheets UX

## Purpose of this Document
This document defines how timesheets in PET are designed to be **accurate, low‑friction, and trustworthy**, while strictly complying with immutability and audit rules.

Timesheets are the highest‑risk adoption surface. PET treats UX quality here as mission‑critical.

---

## Core Principles

- Time entry must be fast, intuitive, and mobile‑friendly
- Accuracy beats convenience
- Users attest to truth at submission
- Corrections are explicit, never destructive

---

## UX Objectives

Timesheet UX must:
- Minimise cognitive load
- Favour defaults over choices
- Make “doing the right thing” the easiest path

Failure here invalidates downstream KPIs, billing, and planning.

---

## Entry Model

### Daily First

- Primary interaction is **daily logging**
- Week‑view is an aggregation, not the primary entry mode

---

### Task‑First Selection

Flow:
1. Select Task
2. Enter duration
3. Confirm classification

Task context is mandatory and non‑bypassable.

---

## Mobile Constraints

Mobile UX must support:
- One‑handed use
- Minimal typing
- Offline capture with deferred submission

Draft entries may exist offline; submission requires connectivity.

---

## Draft vs Submission

- Draft entries are editable
- Submission represents attestation of accuracy
- Submission is explicit, not automatic

Visual distinction between Draft and Submitted is mandatory.

---

## Validation Rules

Hard validation at submission:
- Task required
- Duration > 0
- Classification selected

Failures block submission with explicit feedback.

---

## Corrections

Mistakes are corrected via **compensating entries**.

UX must:
- Make correction obvious
- Preserve original entry visibility
- Link correction to original

Editing submitted entries is never offered.

---

## Feedback to User

Users must see:
- Daily total
- Weekly total
- Planned vs actual (where applicable)

Feedback is informational, not gamified.

---

## What This Prevents

- “I’ll fix it later” behaviour
- Silent edits
- Time laundering

---

**Authority**: Normative

This document defines the UX constraints for PET timesheets.

