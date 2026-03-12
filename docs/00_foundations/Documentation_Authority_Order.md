# PET Documentation Authority Order

## Status
PROPOSED AUTHORITATIVE FOUNDATION DOCUMENT

## Purpose
This document defines the authority order for PET documentation.
Its purpose is to eliminate ambiguity when multiple documents appear to discuss the same domain concept, lifecycle, rule, screen, or implementation behaviour.

Where two documents overlap, the document higher in the authority order governs.
Lower-level documents may explain, derive, or operationalise higher-level documents, but may not contradict them.

## Core rule
PET is document-led.
Code must conform to documentation.
When documentation conflicts, the higher-authority document wins until the conflict is explicitly resolved.

## Authority order

### Level 1 — Foundations
Location:
- `docs/00_foundations/`

Role:
- Defines non-negotiable platform principles, invariants, terminology, and cross-domain governance rules.
- Defines how authority itself works.

Examples:
- immutability principles
- correction model
- event-backed truth model
- authority order
- domain map
- lifecycle model

Binding effect:
- Highest written authority in the repository.
- No lower document may contradict a foundation document.

---

### Level 2 — Architecture
Location:
- `docs/02_architecture/`
- `docs/03_domain_model/`
- `docs/04_cross_cutting_concerns/`
- `docs/05_data_model/`

Role:
- Defines structural architecture, aggregate boundaries, domain entities, relationships, state machines, persistence shape, and cross-cutting platform mechanisms.

Binding effect:
- Must conform to foundations.
- Governs feature/application documents where structure, aggregate boundaries, or lifecycle semantics are in question.

---

### Level 3 — Domain Contracts
Location examples:
- `docs/26_api_contract/`
- `docs/27_event_registry/`
- `docs/32_schema_management/`
- explicit integration contracts in domain folders

Role:
- Defines formal behaviour at domain boundaries.
- Specifies what may be created, read, transitioned, emitted, or rejected.

Binding effect:
- Must conform to foundations and architecture.
- Governs implementation-level behaviour where API, event, or integration semantics are in question.

---

### Level 4 — Domain / Feature Specifications
Location examples:
- `docs/06_features/`
- `docs/07_commercial/`
- `docs/08_quotes/`
- `docs/12_sla_engine/`
- `docs/22_escalation_and_risk/`
- `docs/23_people_resilience/`
- `docs/25_advisory_layer/`
- `docs/31_support_helpdesk/`
- other bounded-domain folders

Role:
- Defines business workflows, domain-specific policies, UI intent, and bounded-context behaviour.

Binding effect:
- Must conform to higher layers.
- If a feature doc conflicts with architecture or a contract, the higher layer wins.

---

### Level 5 — UI, Reporting, Demo, and Derived Operational Documents
Location examples:
- `docs/10_dashboards/`
- `docs/13_ui_structure/`
- `docs/14_ui_components/`
- `docs/16_demo/`
- `docs/30_frontend_shortcodes/`
- planning/readiness docs that operationalise already-defined authority

Role:
- Describes rendering, user journeys, dashboard interpretation, demo execution, and presentational structure.

Binding effect:
- Must not introduce new business rules, lifecycle transitions, or invariants unless those are already defined in a higher layer.

---

### Level 6 — Implementation Planning, Checklists, and Execution Aids
Location examples:
- `docs/15_implementation_blueprint/`
- `docs/28_testing_strategy/`
- `docs/99_trae_prompts/`
- checklists, rollout notes, migration notes, and agent prompts

Role:
- Organises execution.
- Translates authority into implementation work.

Binding effect:
- Never authoritative over domain meaning.
- Must not be treated as the source of truth for entity semantics, lifecycle rules, or domain invariants.

## Conflict resolution rule
If two documents overlap, resolve in this order:

1. check whether one is at a higher authority level
2. if yes, the higher one governs
3. if equal level, prefer the document with narrower and more explicit scope
4. if still ambiguous, prefer the newer document only if it explicitly supersedes the older one
5. if no explicit supersession exists, the conflict must be resolved by documentation correction before implementation proceeds

## Explicit non-rules
The following do **not** by themselves establish authority:
- newer timestamp only
- stronger wording only (`master`, `baseline`, `authoritative`, `full spec`)
- implementation convenience
- existing code behaviour
- agent prompt wording

## Prompt and execution artefact rule
Prompts, checklists, and implementation plans are always derived artefacts.
They may summarise or operationalise authority, but they must not redefine it.
If a prompt differs from a foundation, architecture, contract, or feature document, the prompt is wrong.

## Required practice
When adding a new document, its role must be obvious from:
- folder placement
- filename
- status line
- relation to higher-order authority

## Future correction rule
Where an existing document appears to act above its proper authority level, it must either:
- be replaced with a corrected version
- be moved to the correct layer
- be marked as superseded
- be explicitly constrained in scope
