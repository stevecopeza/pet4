# Repository Document Taxonomy and Placement Rules

## Status
PROPOSED AUTHORITATIVE FOUNDATION DOCUMENT

## Purpose
This document defines how PET documentation should be placed in the repository so that folder location carries reliable meaning.
Its purpose is to reduce authority overlap and make future additions easier to classify correctly.

## Core rule
A document's folder is part of its meaning.
If a document is placed in the wrong folder, readers will infer the wrong authority, ownership, or scope.

## Folder role taxonomy

### Foundations
`docs/00_foundations/`

Use for:
- cross-platform principles
- authority order
- domain map
- lifecycle model
- repository-wide governance rules

Do not use for:
- narrow feature implementation detail
- single-screen UX specs

---

### Overview / onboarding
`docs/01_overview/`
`docs/20_developer_onboarding/`

Use for:
- orientation
- reading order
- high-level explanation
- getting started

Do not use for:
- canonical domain rules

---

### Architecture / domain / data model
`docs/02_architecture/`
`docs/03_domain_model/`
`docs/04_cross_cutting_concerns/`
`docs/05_data_model/`

Use for:
- aggregates
- invariants
- state machines
- structural relationships
- persistence model

Do not use for:
- feature-specific wallboard rendering rules unless those express a true cross-cutting concern

---

### Domain / feature folders
Examples:
- `docs/07_commercial/`
- `docs/08_quotes/`
- `docs/12_sla_engine/`
- `docs/22_escalation_and_risk/`
- `docs/23_people_resilience/`
- `docs/25_advisory_layer/`
- `docs/31_support_helpdesk/`

Use for:
- bounded-context workflows
- local rules and constraints
- local UI intent
- local prohibited behaviours

Do not use for:
- redefining platform-wide principles already owned by higher layers

---

### Contracts and integration boundary docs
`docs/26_api_contract/`
`docs/27_event_registry/`
`docs/17_integrations/`
`docs/38_cross_engine_integration/`

Use for:
- formal boundary behaviour
- payloads
- event semantics
- inter-domain interactions

---

### UI / dashboard / shortcode docs
`docs/10_dashboards/`
`docs/13_ui_structure/`
`docs/14_ui_components/`
`docs/30_frontend_shortcodes/`

Use for:
- rendering structure
- user journeys
- visual grouping
- command surface placement

Do not use for:
- first-definition domain rules
- state-machine legality
- mutation authority

---

### Implementation and execution aids
`docs/15_implementation_blueprint/`
`docs/28_testing_strategy/`
`docs/99_trae_prompts/`

Use for:
- implementation sequencing
- testing plans
- prompts and execution instructions
- migration checklists

Do not use for:
- sole source of truth for business semantics

## Placement decision rule
Before adding a document, ask in order:

1. Is this repository-wide governance?
   - place in foundations
2. Is this canonical structure/invariant/lifecycle/data model?
   - place in architecture/domain/data model
3. Is this a bounded-domain workflow/spec?
   - place in the relevant domain folder
4. Is this a formal API/event/integration boundary?
   - place in contracts/integration folders
5. Is this a view/rendering/shortcode/dashboard explanation?
   - place in UI folders
6. Is this an execution aid?
   - place in implementation/testing/prompt folders

## Anti-shadowing rule
A lower-order folder must not become a shadow authority for a higher-order topic.
Examples of shadowing that should be avoided:
- implementation plan becoming the main place ticket architecture is defined
- UI spec becoming the main place lifecycle transitions are explained
- demo doc becoming the only source for a subsystem's actual behaviour

## Staging rule
Temporary migration content should live in a clearly non-authoritative folder such as:
- `docs/ToBeMoved/`
- `docs/_staging/`

That folder must contain a warning README and must not be treated as active authority.

## Cleanup rule
Packaging noise should be excluded from shared doc packs where possible:
- `.DS_Store`
- `__MACOSX`
- duplicate generated artefacts not needed for authority review
