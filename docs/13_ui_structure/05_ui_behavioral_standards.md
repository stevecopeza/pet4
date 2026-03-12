# PET – UI Behavioral Standards

## Purpose of this Document
This document defines **non-negotiable UI behaviour standards** for PET.

It ensures that all UI implementations:
- Behave consistently across modules
- Reflect domain rules accurately
- Signal risk and irreversibility clearly

This document does **not** define visual design. It defines behaviour.

---

## Core Principles

- UI reflects domain truth; it does not soften it
- Irreversible actions must feel irreversible
- Errors must be explicit, actionable, and consistent
- Schema-driven behaviour is visible, not implicit

---

## Field Behaviour Standards

### Editability by State

Rules:
- Fields are editable **only** while the underlying entity is mutable
- When an entity enters a terminal or locked state:
  - All fields become read-only
  - Inputs are replaced with static representations

Examples:
- Locked Quotes → fully read-only
- Submitted Time Entries → fully read-only
- Completed Projects → fully read-only

---

### Required vs Optional Fields

Rules:
- Required fields are enforced by schema, not UI guesswork
- UI must visually distinguish required fields
- Submission without required fields is blocked, not warned

---

### Deprecated Fields

When a schema version deprecates a field:
- Historical values remain visible
- Field is marked as deprecated
- Field is not editable

The UI must not hide historical meaning.

---

## Error Presentation Standards

### Error Types

Errors fall into three categories:

1. **Validation Errors** – missing or malformed input
2. **Domain Errors** – illegal state or rule violation
3. **System Errors** – infrastructure or integration failure

---

### Presentation Rules

Rules:
- Errors are blocking by default
- Errors are presented inline where possible
- Domain errors must explain *why* the action is forbidden

Errors must never be dismissed silently.

---

### Multiple Errors

When multiple errors exist:
- All must be shown together
- The UI must not force iterative trial-and-error

---

## Irreversible Action Standards

### Actions Covered

Examples include:
- Accepting a Quote
- Submitting Time
- Closing a Project
- Resolving a Ticket with SLA breach

---

### Confirmation Requirements

Rules:
- Explicit confirmation is mandatory
- Confirmation text must state:
  - What will happen
  - What cannot be undone

Generic confirmations are forbidden.

---

### Acknowledgement

After irreversible actions:
- UI must confirm success
- Resulting state must be visible immediately

---

## Schema and Version Awareness

### Schema Version Display

Rules:
- UI must display schema version context where relevant
- Especially for:
  - Malleable fields
  - KPI definitions

Users must understand *why* fields behave differently.

---

### Version Mismatch

When viewing historical data:
- UI must indicate older schema versions
- UI must not reinterpret values

---

## Accessibility and Consistency

Rules:
- Behaviour must be consistent across desktop and mobile
- Keyboard and screen-reader access must be preserved

---

## What This Prevents

- Accidental destructive actions
- UX inconsistency across modules
- Users blaming the system for correct hard blocks
- Developers inventing their own UX rules

---

**Authority**: Normative

This document defines PET’s UI behavioural contract.

