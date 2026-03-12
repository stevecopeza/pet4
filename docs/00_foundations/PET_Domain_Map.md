# PET Domain Map

## Status
PROPOSED AUTHORITATIVE FOUNDATION DOCUMENT

## Purpose
This document defines the top-level PET bounded domains and clarifies where concepts belong.
Its purpose is to reduce duplication, prevent cross-domain drift, and stop multiple folders from becoming parallel authorities for the same concept.

This is a domain map, not a feature backlog.
It defines ownership of concepts.

## Core platform identity
PET is an event-backed, domain-driven operational platform.
It is not a collection of unrelated WordPress screens.
A concept should have one primary domain home, even if many screens surface it.

## Top-level bounded domains

### 1. Commercial
Primary folders:
- `docs/07_commercial/`
- `docs/08_quotes/`

Owns:
- customers and commercial agreements where defined in PET
- quotes and quote versions
- sold scope
- commercial baseline
- rate and value snapshots
- payment schedule concepts tied to sold commercial commitments
- quote-to-project handoff rules

Does not own:
- execution planning after handoff
- support operational lifecycle
- generic dashboard rendering rules

---

### 2. Delivery / Project Execution
Primary folders:
- `docs/06_features/` where legacy material still exists
- `docs/03_domain_model/` for canonical entity/lifecycle shape
- future delivery-specific canonical docs should be kept in one delivery home once rationalised

Owns:
- project entity semantics
- project-level planning state
- WBS/ticket decomposition in delivery context
- operational estimates
- project ticket assignment
- execution progress against sold scope

Does not own:
- quote truth itself
- support ticket semantics that do not belong to project execution

---

### 3. Support Helpdesk
Primary folder:
- `docs/31_support_helpdesk/`

Owns:
- support ticket intake and triage
- support assignment semantics
- support queue/list/wallboard behaviour
- support-oriented ticket operational workflows
- knowledge emergence from support resolution where explicitly defined

Does not own:
- generic ticket architecture at platform level
- commercial quote behaviour
- advisory reporting semantics

---

### 4. SLA Engine
Primary folder:
- `docs/12_sla_engine/`

Owns:
- SLA policies
- calendar/time-window evaluation
- response and resolution timing rules
- breach/warning state derivation
- SLA clock semantics

Does not own:
- generic support workflow except where SLA consequences apply
- dashboards except SLA-derived outputs

---

### 5. Escalation and Risk
Primary folder:
- `docs/22_escalation_and_risk/`

Owns:
- escalation records
- escalation rule evaluation
- acknowledgement/resolution semantics
- operational risk surfacing and actionability

Does not own:
- root ticket lifecycle
- dashboard rendering outside escalation outputs

---

### 6. Advisory Layer
Primary folder:
- `docs/25_advisory_layer/`

Owns:
- derived advisory signals
- advisory snapshots and reports
- QBR-style derived outputs
- versioned, immutable advisory artefacts

Does not own:
- source operational truth
- mutation of customer/project/support state

---

### 7. People Resilience
Primary folder:
- `docs/23_people_resilience/`

Owns:
- resilience requirements
- skills/risk/coverage interpretation for resilience outcomes
- resilience analysis runs and derived resilience signals

Does not own:
- general employee CRUD semantics
- generic team management UI unless explicitly resilience-focused

---

### 8. Work Domain / People / Skills
Primary folders:
- `docs/34_work_domain/`
- `docs/41_governance_people/` where governance-specific overlays exist

Owns:
- roles
- skills
- proficiency concepts
- certifications
- assignments where they are person/work capability concerns rather than ticket execution workflow concerns

Does not own:
- sold commercial baseline
- support-specific operational policy
- advisory report semantics

---

### 9. Event Backbone
Primary folders:
- `docs/27_event_registry/`
- `docs/40_event_backbone/`

Owns:
- event naming and emission semantics
- projection-related event contracts
- event registry and event discipline

Does not own:
- business meaning of source aggregates beyond event contracts

---

### 10. Finance / External Accounting Boundary
Primary folder:
- `docs/39_finance_qb_first/`

Owns:
- PET ↔ accounting boundary semantics
- what synchronises out and back
- finance visibility rules derived from external finance outcomes

Does not own:
- operational truth mutation inside PET

## Cross-domain concept placement rules

### Ticket
Primary canonical shape belongs to:
- `docs/03_domain_model/`
- supported by contracts and event registry where needed

Domain-specific use of tickets may be described in:
- delivery docs
- support helpdesk docs
- SLA docs

But those documents must not redefine the canonical ticket backbone.

### Assignment
Assignment may appear in more than one domain.
Placement rule:
- capability/role/team structure belongs in work domain docs
- operational ticket assignment behaviour belongs in delivery/support docs
- API/event forms belong in contract docs

### Lifecycle
Placement rule:
- canonical state machine semantics belong in architecture/domain model docs
- feature docs may describe how those states are used, but may not redefine legal transitions

### Dashboard / Wallboard / Shortcode views
Placement rule:
- views surface domain truth
- they do not own domain truth
- they must not define creation or mutation rules unless those rules already exist in higher-order docs

## Anti-duplication rule
A concept may be mentioned in many places, but should have only one canonical ownership home.
Any document that discusses a concept it does not own must either:
- reference the canonical home
- explicitly constrain its scope to local usage
- avoid redefining the concept

## Future rationalisation rule
Where the repository currently contains older and newer material for the same concept, future cleanup should aim for:
- one canonical home
- one clear authority path
- explicit supersession of older parallel descriptions
