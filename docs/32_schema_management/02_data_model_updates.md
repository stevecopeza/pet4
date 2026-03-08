# PET – Schema Management Data Model

## Purpose
Defines the database and entity changes required to support the Schema Management workflow.

---

## Database Changes

### Table: `pet_schema_definitions`

Existing columns:
- `id` (PK)
- `entity_type`
- `version`
- `schema_json`
- `created_at`
- `created_by_employee_id`

**New Columns Required:**

| Column | Type | Nullable | Description |
| :--- | :--- | :--- | :--- |
| `status` | `varchar(20)` | No | Enum: `draft`, `active`, `historical`. Default: `draft` for new rows. |
| `published_at` | `datetime` | Yes | Timestamp when status changed to `active`. |
| `published_by` | `bigint` | Yes | Employee ID who performed the publish action. |

### Indexes
- Unique Index: `(entity_type, version)` (Existing - **Must exclude Drafts?**)
    - *Correction*: Version numbers are assigned sequentially. Drafts *should* have a provisional version or use `NULL` until published?
    - *Decision*: Drafts have `version = next_version`. If a draft is deleted, that version number is freed. Since we only allow **one draft per entity type**, this is manageable.
    - *Better Approach*: Drafts have `version = 0` or similar? No, the UI wants to show "Drafting Version 5".
    - *Constraint*: Uniqueness applies to `(entity_type, version)`.

---

## Entity: `SchemaDefinition`

### Status Enum
```php
enum SchemaStatus: string {
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case HISTORICAL = 'historical';
}
```

### Invariants
1.  **Single Draft**: Only one `draft` allowed per `entity_type`.
2.  **Single Active**: Only one `active` allowed per `entity_type`.
3.  **Immutable Active**: Once `status` is `active`, `schema_json` cannot be modified.
4.  **Forward Only**: Publishing a draft makes the current `active` (if any) become `historical`.

---

## Migration Strategy

1.  **Step 1**: Add columns via `dbDelta`.
2.  **Step 2**: Backfill existing rows.
    - Set `status = 'active'` for the latest version of each entity.
    - Set `status = 'historical'` for all older versions.
    - Set `published_at = created_at` for existing rows.

---

**Authority**: Normative
