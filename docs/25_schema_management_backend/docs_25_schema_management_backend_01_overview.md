# PET – Schema Management Backend Overview

## Purpose
This document defines the **backend implementation requirements** to support the Schema Management UI (defined in `docs/24_schema_management_ui`).

It addresses the gap between the existing simple storage model and the robust, status-driven workflow required by the UI.

---

## The Implementation Gap

| Requirement | Current State | Required State |
| :--- | :--- | :--- |
| **Drafting** | No draft concept; all schemas are just "versions". | **Draft** status separate from **Active**. Drafts are mutable; Active is immutable. |
| **Validation** | No validation; any JSON is accepted. | Strict **Field Type** validation (Text, Number, etc.) before saving. |
| **API** | No API endpoints. | Full REST API for List, Create Draft, Update, Publish. |
| **Publishing** | No explicit publish action. | Atomic **Publish** action that locks the schema and bumps the version. |

---

## Core Components to Build

1.  **Database Update**: Add `status` and `published_at` to `pet_schema_definitions`.
2.  **Domain Entities**: Update `SchemaDefinition` to handle status transitions.
3.  **Validator Service**: `SchemaValidator` to enforce field types and structure.
4.  **API Controller**: `SchemaController` to expose REST endpoints.

---

**Authority**: Normative
