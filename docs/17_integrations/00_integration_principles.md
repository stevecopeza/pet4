# PET – Integration Principles

## Purpose
Defines the universal rules governing all external integrations in PET.

---

## Core Principles

- PET is authoritative for operational truth
- External systems never mutate PET state directly
- Integrations are event-driven
- All integrations are idempotent
- Failures are explicit and visible

---

## Trigger Model

- Integrations are triggered by domain events
- UI actions never call integrations directly

---

## Success Semantics

An integration is successful only when:
- External system acknowledges receipt
- PET records a confirmation event

---

## Failure Semantics

- Failures do not mutate domain state
- Failures generate events
- Retries are controlled and finite

---

## Human Resolution

Certain failures require explicit human action before retry.

---

**Authority**: Normative

