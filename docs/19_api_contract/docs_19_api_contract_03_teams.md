# Teams API Contract

## Base Route

/pet/v1/teams

------------------------------------------------------------------------

## 1. List Teams

### GET `/pet/v1/teams`

Returns hierarchical team structure.

### Query Parameters

-   include_archived (boolean, optional)

### Response

``` json
[
  {
    "id": 10,
    "name": "Support",
    "parent_team_id": null,
    "manager_id": 4,
    "escalation_manager_id": 2,
    "status": "active",
    "visual": {
      "type": "system",
      "ref": "support_icon",
      "version": 3
    },
    "member_ids": [22, 45, 67],
    "children": []
  }
]
```

### Rules

-   Returns full hierarchical tree.
-   Archived teams excluded by default.
-   Visual version must match stored version.
-   Includes `member_ids` array of Employee IDs.

------------------------------------------------------------------------

## 2. Retrieve Single Team

### GET `/pet/v1/teams/{id}`

Returns:

-   Core fields
-   Visual identity
-   Escalation target
-   Parent reference
-   Members list (`member_ids`)

------------------------------------------------------------------------

## 3. Create Team

### POST `/pet/v1/teams`

### Payload

``` json
{
  "name": "Tier 2 Support",
  "parent_team_id": 10,
  "manager_id": 5,
  "escalation_manager_id": 2,
  "status": "active",
  "visual": {
    "type": "system",
    "ref": "tier2_icon"
  },
  "member_ids": [101, 102]
}
```

### Domain Enforcement (Server Side)

-   Unique name within parent
-   No circular hierarchy
-   No circular escalation chain
-   Escalation target must be active
-   Manager must be active employee
-   Members must be valid employees

------------------------------------------------------------------------

## 4. Update Team

### PUT `/pet/v1/teams/{id}`

Allowed Changes:

-   name
-   parent_team_id
-   manager_id
-   escalation_manager_id
-   status (active → archived only if safe)
-   visual (must increment visual_version)
-   member_ids (replaces existing membership)

Not Allowed:

-   Hard delete
-   Direct DB mutation bypassing domain
-   Escalation logic in controller layer

------------------------------------------------------------------------

## 5. Archive Team

### POST `/pet/v1/teams/{id}/archive`

Server Enforcement:

-   No active child teams
-   Escalation reassigned or validated
-   No active routing dependencies
-   No hard delete

------------------------------------------------------------------------

## Architectural Guardrails

-   Controllers must remain thin
-   Application layer handles commands
-   Domain layer enforces invariants
-   No business logic in UI layer
-   All hierarchy and escalation validation enforced in Domain
