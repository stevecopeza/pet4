# PET – Implementation Blueprint: Overall Code Structure

## Purpose of this Document
This document defines the **concrete implementation blueprint** for PET inside WordPress.

It translates the entire documentation set into:
- PHP code structure
- Layer boundaries
- Responsibility ownership

This is the reference document developers build against.

---

## Core Principle

**PET is a domain-driven application hosted inside WordPress.**

WordPress provides:
- Runtime
- Auth
- Plugin lifecycle

PET provides:
- Domain logic
- Data integrity
- Business rules

---

## Plugin Root Structure

```
pet/
├─ pet.php                    # Plugin bootstrap
├─ composer.json
├─ src/
│  ├─ Domain/
│  ├─ Application/
│  ├─ Infrastructure/
│  └─ UI/
├─ migrations/
├─ assets/
│  ├─ js/
│  └─ css/
├─ templates/
└─ tests/
```

---

## Layer Responsibilities

### Domain Layer (`src/Domain`)

Contains:
- Entities
- Value objects
- State machines
- Domain services
- Domain events

Rules:
- No WordPress dependencies
- No database access
- No HTTP or UI concerns

---

### Application Layer (`src/Application`)

Contains:
- Use cases (commands)
- Query services
- Transaction orchestration

Rules:
- Coordinates domain objects
- Emits domain events
- No direct WordPress calls

---

### Infrastructure Layer (`src/Infrastructure`)

Contains:
- Repositories (DB)
- Event store
- External integrations (QuickBooks, Twilio)
- Migration execution

Rules:
- Implements interfaces defined by Domain/Application
- Isolates WordPress APIs

---

### UI Layer (`src/UI`)

Contains:
- REST controllers
- Admin menu registration
- SPA bootstrapping
- Classic WP admin pages

Rules:
- No business logic
- Permission checks delegated

---

## Event Flow

```
UI → Application → Domain → Event
                   ↓
             Infrastructure (persist)
```

Events are recorded transactionally.

---

## Dependency Direction

Allowed:
- UI → Application
- Application → Domain
- Infrastructure → Domain

Forbidden:
- Domain → WordPress
- Domain → Infrastructure

---

## Testing Strategy

- Domain: pure unit tests
- Application: use-case tests
- Infrastructure: integration tests

---

## What This Prevents

- WordPress bleed into domain logic
- God classes
- Untestable business rules

---

**Authority**: Normative

This document defines PET’s code architecture.

