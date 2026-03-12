# PET – Core Tables and Keys

## Purpose of this Document
This document defines the **core database table patterns**, primary key strategy, and referential rules used across PET.

It intentionally avoids listing every column for every table. Its role is to establish **structural consistency** so detailed schemas can be implemented safely.

---

## Primary Key Strategy

### Surrogate Keys Only

Rules:
- Every core table uses a surrogate primary key
- Keys are either:
  - BIGINT (auto‑increment), or
  - UUID (v7 preferred)

Natural keys (emails, names, codes) are **never** primary identifiers.

---

### Stability

- Primary keys never change
- Business identifiers may change without affecting identity

This guarantees referential stability across time.

---

## Archival Pattern

### No Hard Deletes

Rules:
- Core tables include an `archived_at` timestamp
- `NULL` = active
- Non‑NULL = archived

Archival:
- Removes records from default queries
- Preserves all relationships

---

## Audit Columns (Standardised)

All core tables include:

- `created_at`
- `created_by_employee_id`
- `archived_at` (nullable)

Optional but common:
- `updated_at` (only for mutable, pre‑terminal states)

---

## Foreign Key Strategy

### Logical First, Physical Where Safe

Rules:
- Referential integrity is enforced in the **domain layer**
- Physical foreign keys are used only when:
  - Cycles are impossible
  - Migration order is deterministic

This avoids deadlocks and migration fragility.

---

## Core Table Patterns

### Identity Tables

Examples:
- `employees`
- `teams`
- `customers`
- `sites`
- `contacts`

Characteristics:
- Long‑lived
- Rarely archived
- Referenced widely

---

### Transactional Tables

Examples:
- `leads`
- `quotes`
- `sales`
- `projects`
- `time_entries`
- `tickets`

Characteristics:
- High write volume
- Append‑heavy
- Event‑emitting

---

### Versioned Tables

Examples:
- `quote_versions`
- `kpi_definition_versions`
- `schema_versions`

Characteristics:
- Immutable once created
- Linked to parent entity

---

## Indexing Principles

Rules:
- Index primary keys automatically
- Index all foreign key columns
- Index:
  - `created_at`
  - `archived_at`
  - `state`

Composite indexes are preferred for frequent filters (e.g. `(project_id, state)`)

---

## State Columns

State machines are represented via:
- Explicit `state` columns
- Enumerated allowed values (validated in domain layer)

State history is captured via events, not table mutation.

---

## Tenant Assumptions

PET is single‑tenant per WordPress install.

Rules:
- No `tenant_id` columns
- Global uniqueness is assumed

---

## What This Prevents

- Referential drift
- Irreversible migrations
- Accidental data loss
- Performance collapse at scale

---

## See also: Ticket Backbone

The Ticket Backbone data model extension defines Ticket as the universal work unit and introduces the bridging required between tickets, tasks, time entries, and quotes.

Related documents:

- `05_data_model/02_Ticket_Data_Model_and_Migrations_v1.md`
- `09_time/05_Time_Entry_Ticket_Enforcement_v1.md`

---

**Authority**: Normative

This document defines the structural rules for PET’s core tables.
