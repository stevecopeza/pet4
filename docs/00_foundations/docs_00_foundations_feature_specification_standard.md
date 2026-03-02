# PET Feature Specification Standard

## Purpose
To prevent integration drift and ensure robust, domain-driven implementation, all new PET features and concepts must adhere to this 4-part specification package. Before implementation begins, the feature request must explicitly define these four sections.

---

## 1. Structural Specification (What it is)
Defines the static and dynamic properties of the entity or concept.
- **Fields**: Data structure, types, and constraints.
- **Invariants**: Rules that must always be true (e.g., "Total must equal sum of parts").
- **State Transitions**: Valid lifecycle states and allowed transitions.
- **Events**: Domain events emitted during lifecycle changes.
- **Persistence**: Database schema, repositories, and storage mechanisms.
- **API**: REST endpoints, request/response contracts.

---

## 2. Lifecycle Integration Contract (When it exists)
Defines the relationship with parent entities and the system lifecycle.
*Key Question:* "In the lifecycle of the parent entity, when does this exist, when must it not exist, and what triggers its creation?"

Must explicitly define:
- **Render Rules**: When is this component visible or applicable?
- **Creation Rules**: What specific triggers cause this entity to be created?
- **Mutation Rules**: When is this entity allowed to change?

---

## 3. Negative Guarantees (Prohibited Behaviours)
Defines what the system **must not** do. This section prevents default behaviors and "magic" logic from creeping in.
*Examples:*
- Must not auto-create on quote creation.
- Must not render unless block exists.
- Must not mutate after acceptance.
- Must not inject defaults.

---

## 4. Stress-Test Scenarios (Integration-Level)
Defines "cross-boundary" stress tests to validate lifecycle integrity, not just local invariants.
*Examples:*
- **New Draft**: Schedule should not exist.
- **Acceptance**: Accept quote without schedule -> Allowed.
- **Cloning**: Clone accepted quote -> Schedule copied but editable.
- **Dependency Deletion**: Delete other blocks -> Schedule unaffected.
- **Isolation**: No other blocks present -> Schedule still hidden.

---

## The Upgrade Path
When introducing any new PET concept, the implementation request must start with:
> "Full Integration Contract, including negative guarantees and lifecycle flows."
