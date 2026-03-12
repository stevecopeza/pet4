# PET Lifecycle Model

## Status
PROPOSED AUTHORITATIVE FOUNDATION DOCUMENT

## Purpose
This document defines the lifecycle model categories used across PET and clarifies where lifecycle semantics must live.
Its purpose is to stop lifecycle drift across feature docs, UI docs, and implementation plans.

This document does not replace aggregate-specific state machines.
It governs how lifecycle documentation should be structured.

## Core principle
Lifecycle rules are domain rules.
They must be defined in domain/architecture authority, not invented in UI docs, dashboard docs, or implementation prompts.

## Lifecycle categories

### 1. Commercial lifecycle
Examples:
- quote draft
- quote sent
- quote accepted
- quote superseded
- payment schedule states where commercially defined

Primary ownership:
- commercial/quote docs
- supported by domain model and contracts

Key rule:
Commercial acceptance freezes sold truth.
Downstream planning may evolve, but sold commercial baseline does not mutate.

---

### 2. Delivery lifecycle
Examples:
- project creation from sold quote
- PM review/planning
- delivery activation
- execution completion/closure
- project-context ticket progression

Primary ownership:
- canonical state semantics in domain model / architecture docs
- usage within delivery feature docs

Key rule:
Delivery planning may refine execution shape without mutating commercial truth.

---

### 3. Support lifecycle
Examples:
- support intake
- triage
- assignment
- work in progress
- resolution
- closure

Primary ownership:
- support helpdesk docs for support-specific flow
- canonical ticket state semantics in domain model where ticket backbone rules apply

Key rule:
Support operational flow may surface SLA consequences, but SLA state is not the same as support workflow state.

---

### 4. SLA lifecycle / clock lifecycle
Examples:
- clock active
- paused where legally supported
- warning
- breached
- satisfied

Primary ownership:
- SLA engine docs

Key rule:
SLA state is derived from timing policy and calendar evaluation, not from arbitrary UI changes.

---

### 5. Escalation lifecycle
Examples:
- opened
- acknowledged
- resolved

Primary ownership:
- escalation and risk docs

Key rule:
Escalation lifecycle must never silently redefine the lifecycle of its source entity.
An escalation is an action/governance artefact, not the source operational truth itself.

---

### 6. Advisory lifecycle
Examples:
- signal generation
- report generation
- versioned publication/snapshotting

Primary ownership:
- advisory layer docs

Key rule:
Advisory outputs are derived and immutable.
They are not source operational truth and must not overwrite it.

---

### 7. People resilience lifecycle
Examples:
- requirement creation
- analysis run
- signal generation
- review/mitigation follow-up if defined

Primary ownership:
- people resilience docs

Key rule:
Resilience analysis is derived.
It must not silently mutate base people/work records.

## Canonical lifecycle placement rule
For any aggregate or stateful domain artefact, lifecycle documentation should be split as follows:

### A. Canonical legality
What states exist and what transitions are legal.

Must live in:
- `docs/03_domain_model/`
- architecture/cross-cutting docs if wider than one aggregate

### B. Boundary behaviour
What API/event/integration surfaces may trigger transitions.

Must live in:
- contracts
- event registry
- integration contracts

### C. Screen usage
How users see and invoke already-defined transitions.

May live in:
- UI structure
- UI components
- shortcode/frontend docs
- dashboard docs

But these may not invent new lifecycle rules.

## Forbidden lifecycle documentation patterns
The following are prohibited:

1. UI docs defining legal state transitions for the first time
2. prompts redefining lifecycle semantics
3. dashboard docs becoming the only place where a lifecycle is explained
4. feature docs contradicting canonical state-machine docs
5. multiple folders each claiming to contain the canonical lifecycle for the same aggregate

## Lifecycle reference rule
Whenever a feature document discusses a lifecycle it does not canonically own, it should:
- reference the canonical lifecycle location
- describe only local usage or constraints
- avoid restating the full lifecycle unless necessary

## Practical correction rule
If two documents appear to define the same lifecycle differently:
- the domain/architecture authority wins
- the lower-order document must be corrected or superseded
- implementation must not proceed based on an unresolved conflict
