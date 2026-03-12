# PET – Implementation Blueprint: Domain Layer

## Purpose
Defines how PET domain concepts are implemented in PHP, enforcing invariants, state machines, and event emission.

---

## Structure

```
src/Domain/
├─ Entity/
├─ ValueObject/
├─ Service/
├─ Event/
└─ Exception/
```

---

## Entities

Rules:
- One class per domain entity
- Identity via constructor injection
- State transitions via explicit methods only

Entities never:
- Call WordPress APIs
- Access databases

---

## State Machines

- Implemented as guarded methods
- Illegal transitions throw domain exceptions
- State changes emit domain events

---

## Domain Events

- Immutable value objects
- Named according to schema (`quote.accepted`)
- Emitted synchronously

---

## Domain Services

Used only when logic does not belong to a single entity.

---

## Exceptions

- Represent business rule violations
- Never caught silently

---

**Authority**: Normative

