# PET – Core vs Extension Enforcement Checklist (TRAE)

## Purpose
Operational checklist to ensure TRAE does not violate the Core vs Extension Field Policy during implementation.

---

## Before Creating or Modifying Any Field

TRAE MUST verify:

1. Does this field participate in a state machine?
2. Is this field referenced by another aggregate?
3. Is this field required for an invariant?
4. Is this field emitted in a domain event?
5. Is this field used in integrations?
6. Is this field used in KPI derivation?
7. Does this field define identity?

If ANY answer is YES →

→ The field MUST be implemented as a Core field via migration.
→ It MUST NOT be created via the field creator.

---

## When Implementing Schema Management

TRAE MUST ensure:

- Core fields are excluded from the field creator UI.
- Core fields are not removable.
- Field name validation blocks semantic overlap with core fields.
- Core and Extension fields are stored separately.

---

## When Implementing APIs
 
 TRAE MUST ensure:
 
 - Core and Extension fields are clearly separated in DTOs.
 - Extension fields are namespaced or grouped in the API response.
 - No extension field is used to drive domain logic.

---

## When Reviewing Feature Requests

If a request attempts to:
- Make billing behaviour configurable
- Make state transitions configurable
- Make relationships configurable

Then the field MUST be promoted to Core and documented.

---

**Authority**: Normative

This checklist is mandatory for TRAE during implementation.

