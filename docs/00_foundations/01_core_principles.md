# PET – Core Principles

## Purpose of this Document
This document defines the **non‑negotiable principles** that govern all design, implementation, and future evolution of PET.

If a feature, shortcut, or refactor violates one of these principles, it is **invalid**, regardless of perceived business value or developer convenience.

---

## Principle 1: Measurement Is Primary

PET is designed so that **measurement is a first‑order concern**, not a reporting layer.

Implications:
- All meaningful actions must emit measurable events
- KPIs are derived from operational facts, not manually entered scores
- Dashboards consume the same data that operations produce

If something cannot be measured, it is considered **structurally incomplete**.

---

## Principle 2: Time Is the Universal Currency

All commercial activity ultimately reconciles to **time**.

This includes:
- Fixed‑fee projects
- Hardware and software margin
- SLAs and retainers

Implications:
- Every quote has an implicit or explicit time budget
- Over‑delivery and under‑delivery are always detectable
- Resource levelling is possible by design, even if deferred

Money is an outcome; time is the constraint.

---

## Principle 3: Immutability of Historical Truth

Once a fact has occurred, it must not be silently changed.

Examples:
- Signed quotes are immutable
- Logged time entries are append‑only (corrections create new records)
- KPI source events are never rewritten

Implications:
- Change is represented by **new state**, not mutation
- Corrections are explicit and attributable
- Auditability is preserved without exception

---

## Principle 4: Delta, Not Edit

Change is expressed as a **delta**, not an overwrite.

Examples:
- Quote changes are handled via cloned or delta quotes
- Scope changes create adjustments, not edits
- Re‑planning respects original sold constraints

This principle ensures that:
- Commercial intent remains visible
- Financial reconciliation is tractable
- Trust is maintained with customers and auditors

---

## Principle 5: Derived KPIs, Influenced Inputs

KPIs are always **derived**, never directly authored.

Managers may influence:
- Targets and thresholds
- Weightings and classifications
- What constitutes success or failure

Managers may not:
- Edit historical source data
- Override computed outcomes

This preserves factual integrity while allowing managerial judgement.

---

## Principle 6: Single Source of Operational Truth

PET is the authoritative system for:
- Sales intent
- Delivery commitments
- Resource allocation
- Operational KPIs

External systems (e.g. accounting) are authoritative only within their domain.

Implications:
- QuickBooks is the system of record for accounting
- PET remains authoritative for operational reality

---

## Principle 7: Safety Over Convenience

When forced to choose, PET prioritises:

- Data safety over ease of editing
- Explicit workflows over implicit magic
- Guardrails over flexibility

This is intentional. Convenience can be layered later; corrupted history cannot.

---

## Principle 8: Malleability Is Structured, Not Ad‑Hoc

Flexible fields are:
- Schema‑driven
- Typed
- Versioned
- Context‑scoped

They are **not** arbitrary custom fields scattered across the system.

Schema changes are forward‑only; history retains its original shape.

---

## Principle 9: Everything Has Context

All meaningful records must be attributable to context, including:
- Customer
- Site
- Contact
- Project
- Task
- Ticket

Lack of context is treated as a system failure, not a user error.

---

## Principle 10: Auditability Is Non‑Optional

PET assumes that any decision, action, or metric may need to be explained later.

Therefore:
- Who did what, when, and why must be reconstructable
- Activity feeds prioritise factual accuracy
- Social or narrative layers are secondary

---

## Principle 11: WordPress Is an Implementation Detail

WordPress provides:
- Authentication
- Hosting
- Plugin lifecycle

WordPress does **not** define:
- Domain rules
- Data lifecycles
- Business invariants

Domain logic lives above the CMS.

---

## Principle 12: Long‑Lived System Assumption

PET is designed for:
- Multi‑year use
- Skipped upgrades
- Schema evolution over time

Backward compatibility is mandatory.

---

**Authority**: Normative

This document constrains all current and future PET development.

