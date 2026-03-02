# PET – API Contract: Standard Endpoints

## Purpose
Defines the standard REST API patterns for Entity management, ensuring consistency across all modules (People, Projects, Knowledge, etc.).

## Standard CRUD Pattern

All core entities MUST implement the following endpoint structure:

### 1. List / Search
- **Endpoint**: `GET /pet/v1/{resource}`
- **Response**: Array of entity objects.
- **Filtering**: Query parameters (e.g., `?category=technical`).

### 2. Create
- **Endpoint**: `POST /pet/v1/{resource}`
- **Payload**: JSON object containing core fields + `malleableData`.
- **Response**: 201 Created with message.

### 3. Update
- **Endpoint**: `PUT /pet/v1/{resource}/{id}`
- **Payload**: JSON object containing fields to update + `malleableData`.
- **Response**: 200 OK with message.
- **Note**: WordPress `register_rest_route` uses `WP_REST_Server::EDITABLE` which accepts POST/PUT/PATCH, but frontend uses `PUT` semantically.

### 4. Archive (Soft Delete)
- **Endpoint**: `DELETE /pet/v1/{resource}/{id}`
- **Response**: 200 OK with message.
- **Behavior**: Sets `archivedAt` timestamp; entity remains in DB.

## Malleable Fields Payload
For entities supporting dynamic schemas, the payload MUST include a `malleableData` object.

**Request Body Example:**
```json
{
  "firstName": "John",
  "lastName": "Doe",
  "email": "john@example.com",
  "malleableData": {
    "mobile": "+1234567890",
    "department": "Engineering",
    "isRemote": true
  }
}
```

## Error Handling
- **400 Bad Request**: Validation failure (missing fields, invalid data).
- **404 Not Found**: Entity ID does not exist.
- **403 Forbidden**: Insufficient permissions.
- **500 Internal Server Error**: Unexpected system failure.

**Authority**: Normative
