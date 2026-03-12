# PET – Schema Guardrails & Error States

## Purpose
Defines **hard guardrails** and error behaviour for schema management.

---

## Forbidden Actions

- Modifying Active schemas
- Deleting fields with historical data
- Changing field types after use

All forbidden actions must **hard fail**.

---

## Error Messaging

Errors must:
- Be explicit
- Explain why the action is blocked
- Reference immutability or historical safety

---

## Audit

All schema changes:
- Are recorded as events
- Include actor and timestamp

---

**Authority**: Normative

