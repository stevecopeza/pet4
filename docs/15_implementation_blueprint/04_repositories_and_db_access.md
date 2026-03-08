# PET – Implementation Blueprint: Repositories & DB Access

## Purpose
Defines how PET persists and retrieves domain data safely.

---

## Structure

```
src/Infrastructure/Persistence/
├─ Repository/
├─ Mapper/
└─ Schema/
```

---

## Repositories

Rules:
- One repository per aggregate
- Return domain entities only
- No SQL outside repository

---

## Mappers

- Translate rows ↔ domain objects
- Handle schema versions

---

## Transactions

- Controlled by Application layer
- Repositories are transaction-aware

---

## Anti-Patterns (Forbidden)

- Active Record
- Direct `$wpdb` calls outside infrastructure

---

**Authority**: Normative

