# PET – SYSPRO Context Integration

## Purpose
Defines how PET references SYSPRO context without duplicating ERP authority.

---

## Scope

- PET does not replace SYSPRO
- PET references SYSPRO identifiers and environment context

---

## Data Usage

- Customer ERP IDs
- Site configuration references

---

## Rules

- No transactional sync
- No master data overwrite

---

## Failure Handling

- Missing context is surfaced explicitly
- No fallback assumptions

---

**Authority**: Normative

