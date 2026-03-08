# PET – Migration Execution Model

## Purpose of this Document
This document defines **how database migrations are authored, executed, validated, and recovered** in PET.

Migrations are treated as **critical operations**. A failed migration is safer than a partial one.

---

## Core Principles

- Migrations are forward‑only
- Migrations are deterministic and idempotent
- Partial application is not permitted
- Data loss is never acceptable

---

## Migration Identification

Each migration has:
- A unique, monotonically increasing version identifier
- A human‑readable name

Example:
```
2026_02_10_0001_create_core_tables
```

The version identifier is the authoritative ordering mechanism.

---

## Migration Registry

PET maintains an internal registry table:

`pet_migrations`

Columns:
- `version` (PK)
- `name`
- `applied_at`
- `checksum`

Rules:
- A migration runs only if not present in the registry
- Checksums prevent silent modification

---

## Execution Model

### Activation‑Time Execution

Migrations run:
- On plugin activation
- On plugin upgrade

They do **not** run on every request.

---

### Transaction Boundaries

Rules:
- Each migration runs in a transaction where supported
- If any step fails, the transaction is rolled back

If transactions are not supported (e.g. certain DDL), compensating logic is required.

---

## Idempotency Rules

Migrations must:
- Check for existence before creation
- Be safe to re‑run

Example:
- Create table only if not exists
- Add column only if missing

---

## Forward‑Only Rule

Rules:
- No down migrations
- Rollback is logical (via new migrations)

Reverting behaviour requires a new migration that amends state safely.

---

## Failure Handling

On migration failure:

- Plugin activation is halted
- The system enters maintenance mode for PET features
- Clear error messaging is provided

Partial migrations are unacceptable.

---

## Backward Compatibility Strategy

Rules:
- New code must tolerate old schemas
- Schema version checks are explicit
- Feature flags may be used for staged rollout

Skipping plugin versions is supported.

---

## Data Transformation Migrations

When transforming existing data:

Rules:
- Source data is preserved
- New structures are populated alongside old
- Cut‑over is explicit and reversible

Destructive transforms are forbidden.

---

## Long‑Running Migrations

For large datasets:

- Use chunked background execution
- Track progress explicitly
- Allow safe resumption

User‑facing operations must be blocked during unsafe states.

---

## Observability

Each migration emits events:
- Migration started
- Migration completed
- Migration failed

These events feed audit and support tooling.

---

## What This Prevents

- Half‑applied schemas
- Silent data corruption
- Irreversible upgrades
- Environment‑specific behaviour

---

**Authority**: Normative

This document defines how PET evolves its data safely over time.

