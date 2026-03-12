# PET – Schema Management API Contract

## Purpose
Defines the REST API endpoints for the React UI to manage schemas.

---

## Base Path
`/wp-json/pet/v1/schemas`

## Endpoints

### 1. List Schemas
**GET** `/{entity_type}`

Returns all versions for a specific entity type (Active, Draft, and Historical).

**Response:**
```json
[
  {
    "id": 12,
    "version": 3,
    "status": "active",
    "createdAt": "2023-10-01T10:00:00Z",
    "publishedAt": "2023-10-05T12:00:00Z",
    "fieldCount": 5
  },
  {
    "id": 15,
    "version": 4,
    "status": "draft",
    "createdAt": "2023-10-20T09:00:00Z",
    "fieldCount": 6
  }
]
```

### 2. Get Schema Details
**GET** `/{id}`

Returns full schema definition.

**Response:**
```json
{
  "id": 15,
  "entityType": "customer",
  "version": 4,
  "status": "draft",
  "schema": [
    {
      "key": "industry",
      "label": "Industry",
      "type": "select",
      "options": ["Tech", "Health"],
      "required": true
    }
  ]
}
```

### 3. Create Draft
**POST** `/draft`

Creates a new draft. Fails if a draft already exists for this entity type.
Optionally clones from the current Active version.

**Payload:**
```json
{
  "entityType": "customer",
  "cloneFromActive": true
}
```

**Response:** `201 Created` with Schema Object.

### 4. Update Draft
**PUT** `/{id}`

Updates the field definitions of a **Draft** schema.
Fails if schema is not in `draft` status.
Performs **Validation** before saving.

**Payload:**
```json
{
  "schema": [
    { "key": "industry", ... }
  ]
}
```

### 5. Publish Schema
**POST** `/{id}/publish`

Activates the draft.
1.  Validates integrity.
2.  Sets current `active` to `historical`.
3.  Sets this schema to `active`.
4.  Sets `published_at` to now.

**Payload:** Empty.

### 6. Delete Draft
**DELETE** `/{id}`

Deletes a **Draft** schema only.
Fails if schema is `active` or `historical`.

---

**Authority**: Normative
