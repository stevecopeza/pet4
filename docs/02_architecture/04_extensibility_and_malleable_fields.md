# PET – Extensibility and Malleable Fields

## Purpose of this Document
This document defines **how PET supports flexibility without sacrificing integrity**.

Malleable fields are a first‑class capability, but they are tightly governed. This document exists to prevent schema chaos, KPI corruption, and historical reinterpretation.

---

## Core Principle

**Flexibility is structural, not ad‑hoc.**

PET allows entities to evolve, but never at the expense of:
- Historical meaning
- Auditability
- KPI consistency

---

## Definition: Malleable Field

A **malleable field** is a user‑defined data element whose:
- Presence is configurable
- Type is explicit
- Valid values are constrained
- Meaning is versioned

Malleable fields are not arbitrary metadata.

---

## Scope of Malleable Fields

Malleable fields may exist on:

- Leads
- Customers
- Sites
- Contacts
- Qualification records
- Projects
- Tickets
- Knowledge articles

They may **not** exist on:
- Time entries
- KPI source events
- Financial commitments

These are structurally fixed.

---

## Field Types

Supported field types include:

- Text (short / long)
- Numeric
- Currency (non‑authoritative)
- Date / time
- Boolean
- Single select
- Multi‑select
- URL
- Location

Each field type has:
- Validation rules
- Storage constraints
- Display hints

---

## Schema Versioning

Malleable fields are grouped into **schemas**.

Rules:
- Schemas are versioned
- Changes create a new schema version
- Records reference the schema version they were created under

Schema versions are immutable once in use.

---

## Forward‑Only Evolution

Schema changes apply **forward only**.

Historical records:
- Retain original values
- Retain original meaning
- Are never reinterpreted

This preserves analytical and legal integrity.

---

## Value Semantics

For constrained fields (e.g. dropdowns):

- Stored values are stable identifiers
- Labels are presentation only
- Deprecated options remain resolvable

Removing an option does not invalidate history.

---

## KPI Interaction

Rules:
- KPIs may reference malleable fields
- KPI definitions bind to **specific schema versions**
- KPI recalculation respects original semantics

Changing a field does not retroactively change KPIs.

---

## Permissions and Governance

- Schema creation and changes are restricted
- Changes are auditable events
- Schema ownership is explicit

Malleability is a governed capability, not a convenience feature.

---

## UI Implications

- Forms are rendered from schema definitions
- Validation is consistent across UI and API
- Users cannot bypass required fields

UI convenience does not override schema rules.

---

## What This Prevents

- “Just add a field” culture
- KPI drift over time
- Rewriting history to fit new thinking
- Irreversible schema mistakes

---

**Authority**: Normative

This document defines how PET evolves safely.

