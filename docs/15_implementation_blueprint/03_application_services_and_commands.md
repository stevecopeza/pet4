# PET – Implementation Blueprint: Application Layer

## Purpose
Defines how PET coordinates use cases via application services.

---

## Structure

```
src/Application/
├─ Command/
├─ Handler/
├─ Query/
└─ DTO/
```

---

## Commands

- Represent intent (e.g. `AcceptQuote`)
- Contain no logic

---

## Handlers

- Orchestrate domain objects
- Enforce transactions
- Emit events

---

## Queries

- Read-only
- No domain mutation

---

## Transactions

- Begin in handler
- Commit only if domain succeeds

---

**Authority**: Normative

