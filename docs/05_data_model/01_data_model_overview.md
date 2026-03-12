# PET – Data Model & Migrations: Overview

## Purpose of this Document
This document defines the **authoritative data model strategy** for PET and how schema evolution is handled over time.

It translates previously agreed **domain entities, invariants, and architecture** into concrete persistence rules, without prematurely locking low-level SQL details.

This document is the entry point for all database design and migration work.

---

## Core Principles (Non‑Negotiable)

- Custom database tables for all core operational data
- No WordPress `posts` / `postmeta` for domain truth
- Forward‑only migrations
- Backward compatibility is mandatory
- Data loss is never acceptable

---

## Table Categories

PET tables are grouped into **clear categories**:

### 1. Identity & Organisation

- employees
- teams
- team_memberships
- org_structure_versions

Purpose:
- Authority
- Visibility
- KPI scoping

---

### 2. Commercial Pipeline

- leads
- lead_schema_versions
- qualifications
- opportunities
- quotes
- quote_versions
- quote_line_groups
- quote_lines
- sales

Purpose:
- Capture intent
- Enforce immutability
- Preserve commercial history

---

### 3. Delivery

- projects
- project_sold_constraints
- milestones
- tasks
- project_variances

Purpose:
- Enforce sold vs delivered
- Track execution reality

---

### 4. Time & Resources

- time_entries
- time_entry_adjustments
- resource_allocations

Purpose:
- Factual effort recording
- Reconciliation backbone

---

### 5. Support & SLA

- tickets
- sla_definitions
- sla_assignments
- sla_events

Purpose:
- Contextual support
- SLA enforcement

---

### 6. Knowledge

- knowledge_articles
- knowledge_versions
- knowledge_comments

Purpose:
- Organisational learning

---

### 7. Measurement & Audit

- domain_events
- kpi_definitions
- kpi_definition_versions
- kpi_results
- activity_feed_projections

Purpose:
- Immutable truth
- Explainable KPIs

---

## Identity Strategy

### Primary Keys

- All tables use surrogate primary keys (UUID or BIGINT)
- Natural keys are never trusted

---

### Referential Integrity

Rules:
- Logical foreign keys enforced in domain layer
- Physical foreign keys used where safe
- Deletions are blocked at domain level

---

## Schema Versioning Strategy

Schemas exist for:
- Malleable fields
- KPI definitions
- Org structure

Rules:
- Schema versions are immutable
- Records reference schema version explicitly

---

## Event Storage Model

The `domain_events` table:

- Is append‑only
- Stores event type, timestamp, actor, context
- Is the source for KPIs and activity feed

Events are never updated or deleted.

---

## Migration Strategy

### Versioned Migrations

- Each plugin version may include migrations
- Migrations are ordered and idempotent

---

### Forward‑Only Rule

- No down migrations
- Rollback is logical, not physical

---

### Failure Handling

- Migration failure halts plugin activation
- Partial migrations are not permitted

---

## Backward Compatibility

Rules:
- Old data must remain readable
- New code must handle old schema versions

Skipping plugin versions is supported.

---

## What This Enables

- Safe long‑term evolution
- Confident refactoring
- Predictable performance
- Auditable history

---

**Authority**: Normative

This document defines PET’s data model and migration strategy.

