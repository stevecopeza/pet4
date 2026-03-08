# PET – Implementation Blueprint: REST API & UI Contracts

## Purpose
Defines how UI layers interact with PET via REST APIs.

---

## Structure

```
src/UI/Rest/
├─ Controller/
├─ Request/
└─ Response/
```

---

## Controllers

Rules:
- Thin controllers
- Delegate to Application layer
- No business logic

---

## Requests

- Validated DTOs
- Reject invalid state transitions early

---

## Responses

- Never expose internal IDs blindly
- Preserve state and version context

---

## Security

- Capability check (WP)
- Domain permission check (PET)

---

## Error Handling

- Domain errors → explicit API errors
- No silent fallbacks

---

**Authority**: Normative

