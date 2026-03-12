# PET – Schema Versioning & Publish Flow

## Purpose
Defines how schema changes are **activated safely**.

---

## Draft vs Active

- Draft schemas are editable
- Active schema is immutable
- Only one Active schema per entity

---

## Publish Action

Publishing a schema:
- Locks the schema permanently
- Creates a new schema version
- Makes it Active immediately

---

## Publish Confirmation

UI must display:
- Warning that action is irreversible
- Summary of changes
- Explicit confirmation

---

## Effects of Publish

- New records use new schema
- Existing records remain bound to old schema
- APIs expose schema version metadata

---

**Authority**: Normative

