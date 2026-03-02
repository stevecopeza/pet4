# PET – Schema Editor Screen

## Purpose
Defines how admins **add, edit, and remove fields** within a draft schema.

---

## Add Field Flow

Required inputs:
- Field Label
- Field Type (Text, Number, Select, Date, Boolean, etc.)
- Required / Optional
- Default value (optional)

On save:
- Field is added to the **draft schema only**

---

## Edit Field Flow

Editable attributes:
- Label
- Help text
- Display order

Locked attributes once data exists:
- Field type
- Cardinality

Editing creates **no impact** until schema is published.

---

## Remove Field Flow

- Removes field from **future records only**
- Historical records retain the field and value
- Removal only allowed in Draft schema

---

## Validation
- Invalid changes hard fail
- Clear, explicit error messages shown

---

**Authority**: Normative

