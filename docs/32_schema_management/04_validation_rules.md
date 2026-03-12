# PET – Schema Validation Rules

## Purpose
Defines the strict validation logic that the backend must enforce for Malleable Schemas.

---

## Field Structure
Every item in the `schema` array must conform to:

```json
{
  "key": "string (slug, unique per schema)",
  "label": "string (max 100 chars)",
  "type": "enum (see below)",
  "required": "boolean",
  "default": "mixed (optional)",
  "options": "array (optional, required for select/multiselect)"
}
```

## Allowed Field Types

| Type | Description | Config Requirements |
| :--- | :--- | :--- |
| `text` | Short text input | - |
| `textarea` | Long text area | - |
| `number` | Numeric input | `min`, `max` (optional) |
| `boolean` | Checkbox | - |
| `date` | Date picker | - |
| `datetime` | Date & time picker | - |
| `select` | Dropdown | `options` (array of strings) |
| `multiselect` | Multi-select box | `options` (array of strings) |
| `email` | Email input | - |
| `url` | URL input | - |

## Validation Logic

1.  **Key Uniqueness**: No two fields in the same schema can share a `key`.
2.  **Key Format**: Keys must be `[a-z0-9_]+` (snake_case).
3.  **Option Integrity**: If `type` is `select` or `multiselect`, `options` must be a non-empty array.
4.  **Type Validity**: `type` must match the Allowed Field Types list.

---

**Authority**: Normative
