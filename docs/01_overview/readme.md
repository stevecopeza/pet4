# PET – Plan. Execute. Track

PET is a **domain‑driven business operations platform** built as a WordPress plugin for consulting‑led, project‑driven organisations.

It is designed for companies that sell and deliver **software, hardware, projects, and ongoing support**, and that require **accountability, traceability, and trustworthy KPIs** across the entire lifecycle of their work.

---

## What PET Is

PET is not a generic CRM or project tool.

It is an **operating model** that tightly links:

- What was sold
- What is being delivered
- What time was spent
- What was invoiced
- What was learned

PET enforces alignment between these areas by design.

---

## Core Principles

- **Single source of operational truth**
- **Immutability of history** (nothing important is silently edited or deleted)
- **Event‑driven measurement** (KPIs derive from facts, not guesses)
- **Explicit variance and risk** (problems surface early)
- **Long‑lived data safety** (backward compatibility is mandatory)

---

## Organisational Integrity

PET does not only govern work delivery.
It governs the capability required to deliver that work.

- **Roles are versioned and immutable** once published.
- **KPIs are instantiated** from role templates and locked per review cycle.
- **Certifications can enforce compliance** and expiry rules.
- **Skills are assessed** with dual authority and evidence support.
- **Historical truth is preserved** across promotions, restructures, and change.

PET therefore measures not only performance, but **organisational readiness**.

---

## What PET Covers

PET spans the full lifecycle of a service business:

- CRM: leads, qualification, opportunities
- Quoting: version‑controlled, project and recurring pricing
- Sales: win / loss tracking
- Project delivery: milestones, tasks, sold‑vs‑actual enforcement
- Time tracking: mobile‑friendly, task‑anchored timesheets
- Support & SLAs: ticketing with contextual linkage
- Knowledgebase: capture and reuse of solutions
- Capabilities & Organisational Governance
  - Versioned role definitions with KPI templates
  - Structured skill and certification frameworks
  - Multi-role assignment with allocation modelling
  - Compliance tracking with expiry enforcement
  - Dual skill assessment with review-cycle locking
  - Snapshot hiring profiles derived from role baselines
  - Evidence-backed performance history
  - Organisational capability gap visibility
- Dashboards & KPIs: role‑based, explainable metrics

---

## Architecture Overview

PET is built using a **Domain‑Driven Design (DDD)** approach inside WordPress.

High‑level structure:

```
pet/
├─ src/
│  ├─ Domain/          # Business rules and invariants
│  ├─ Application/     # Use cases and orchestration
│  ├─ Infrastructure/  # Database, integrations, persistence
│  └─ UI/              # Admin UI and REST APIs
├─ migrations/         # Forward‑only database migrations
├─ assets/             # JS/CSS for admin UI
└─ docs/               # Authoritative system documentation
```

WordPress provides runtime, authentication, and plugin lifecycle.
All business logic lives inside PET.

---

## Documentation

This repository is **document‑led**.

The `/docs` directory is authoritative and covers:

- System foundations and invariants
- Architecture and domain model
- Data model and migrations
- UI contracts and permissions
- Integration specifications
- Reference implementations
- Stress‑test scenarios
- Developer onboarding

If code conflicts with documentation, **the documentation wins**.

---

## Integrations

PET integrates with external systems (e.g. accounting, messaging, ERP context) using:

- Event‑driven triggers
- Explicit success and failure semantics
- No silent overwrites of PET data

PET always remains the operational authority.

---

## Who PET Is For

PET is intended for:

- Consulting and systems integration businesses
- Organisations delivering projects and ongoing services
- Teams that care about measurement, accountability, and auditability

It is not designed for:

- Simple content management
- Lightweight task lists
- Businesses unwilling to run on facts

---

## Status

PET is an actively designed system with a complete architectural and domain specification.

Implementation follows the documentation in this repository.

---

## Ticket Backbone

The Ticket Backbone correction defines how PET enforces the invariant that **all person work activity is tied to a Ticket** and how tasks, time entries, quotes, projects, and SLAs align to that spine.

Key documents:

- Principles and invariants: `00_foundations/01_Ticket_Backbone_Principles_and_Invariants_v1.md`
- Data model and migrations: `05_data_model/02_Ticket_Data_Model_and_Migrations_v1.md`
- Quote → Ticket → Project flow: `03_commercial/04_Quote_to_Ticket_to_Project_Flow_v1.md`
- Time entry enforcement: `04_time/05_Time_Entry_Ticket_Enforcement_v1.md`
- Roadmap: `08_implementation_blueprint/PET_Ticket_Backbone_Implementation_Roadmap_v1.md`
- Stress tests: `08_implementation_blueprint/10_Stress_Test_Scenarios_Ticket_Backbone_v1.md`
- TRAE implementation prompt (NOT TO BE USED YET): `08_implementation_blueprint/11_TRAE_Prompt_Ticket_Backbone_Implementation_ADD_ONLY_v1.md`

These documents are authoritative for the Ticket Backbone; implementation work must follow them without redesign.

---

## Contributing

Before contributing, **read the Developer Onboarding Guide** in `/docs/12_developer_onboarding/`.

Key rules:
- Do not bypass domain rules
- Do not mutate history
- Do not add business logic to UI or infrastructure layers

---

## License

License details to be defined by the project owner.

---

PET exists to make **planning honest, execution disciplined, tracking meaningful, and capability accountable**.

