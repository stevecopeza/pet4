# PET – High‑Level System Architecture

## Purpose of this Document
This document defines the **structural architecture** of PET as a single, cohesive WordPress plugin with internally enforced modular boundaries.

It explains *what exists*, *where responsibilities live*, and *how data flows*, without prescribing implementation details prematurely.

---

## Architectural Stance

PET is implemented as **one plugin** with:
- A single lifecycle
- A unified upgrade path
- Shared invariants and transaction boundaries

Internal modularisation is **logical, not deployable**.

This avoids:
- Cross‑plugin version drift
- Partial upgrades
- Broken invariants across boundaries

---

## Layered Architecture

PET follows a **layered architecture**, independent of WordPress conventions.

```
┌─────────────────────────────┐
│ Presentation Layer          │  UI, dashboards, forms, APIs
├─────────────────────────────┤
│ Application Layer           │  Use‑cases, workflows, orchestration
├─────────────────────────────┤
│ Domain Layer                │  Rules, invariants, state machines
├─────────────────────────────┤
│ Infrastructure Layer        │  WP DB, cron, mail, integrations
└─────────────────────────────┘
```

WordPress primarily occupies the **Infrastructure** and parts of **Presentation**.

---

## Domain‑First Design

The **Domain Layer** is authoritative.

It contains:
- Entity definitions
- State transitions
- Invariant enforcement
- KPI source events

The domain layer:
- Does not depend on WordPress APIs
- Can be tested independently
- Rejects invalid state regardless of UI

---

## Internal Modules (Logical)

Modules represent **bounded domains**, not deployable plugins.

Initial modules include:

- Identity & Organisation
- Leads & Opportunities
- Quoting & Sales
- Projects & Delivery
- Time & Resources
- Helpdesk & SLA
- Knowledgebase
- Measurement & KPIs
- Activity & Audit

Modules may interact only via **explicit domain interfaces**.

---

## Data Flow Overview

1. **User Action** (UI / API)
2. Application layer validates intent
3. Domain layer evaluates invariants
4. Events are emitted
5. State changes are persisted
6. KPIs update from events
7. Activity feed reflects outcome

Failure at any step **blocks progression**.

---

## Event‑Driven Core

PET uses an **event‑driven internal model**.

- Domain actions emit events
- Events are immutable
- KPIs consume events
- Activity feed renders events

Events are not a messaging system; they are **facts**.

---

## Transaction Boundaries

All operations that:
- Affect commercial commitments
- Log time
- Change project state

Must execute within **explicit transaction boundaries**.

Partial failure is not acceptable.

---

## Integration Boundaries

External systems (e.g. QuickBooks) interact via **adapters**.

Rules:
- PET never assumes external success
- External failures do not corrupt PET state
- Reconciliation is explicit and traceable

---

## Upgrade Strategy

Because PET is single‑plugin:

- Schema migrations are coordinated
- Invariants evolve safely
- Backward compatibility is enforced

Skipping versions is supported.

---

## What This Architecture Enables

- Safe malleable schemas
- Strong auditability
- Deterministic KPIs
- Long‑term maintainability

---

**Authority**: Normative

This document defines PET’s structural shape. Implementation must conform.

