# Org API Contract

## Purpose

Defines the REST API contract for the organisational structure (Org),
which represents the top-level structural container above Teams.

Org is operationally binding and governs:

-   Structural hierarchy root
-   Governance defaults
-   Reporting boundaries
-   Escalation fallbacks
-   Cross-team aggregation

------------------------------------------------------------------------

## Base Route

/pet/v1/org

------------------------------------------------------------------------

## 1. Retrieve Org Structure

### GET `/pet/v1/org`

Returns full organisational structure including nested teams.

### Response

``` json
{
  "id": 1,
  "name": "Company Name",
  "status": "active",
  "visual": {
    "type": "upload",
    "ref": "org_logo_v2",
    "version": 2
  },
  "default_escalation_manager_id": 3,
  "teams": []
}
```

### Rules

-   Single authoritative Org record.
-   Must include default escalation team.
-   Must include visual identity version.
-   Teams returned as hierarchical tree.

------------------------------------------------------------------------

## 2. Update Org

### PUT `/pet/v1/org`

Allowed Changes:

-   name
-   visual (must increment visual_version)
-   default_escalation_manager_id
-   status (active → archived only if safe)

### Payload

``` json
{
  "name": "New Company Name",
  "default_escalation_manager_id": 3,
  "visual": {
    "type": "upload",
    "ref": "org_logo_v3"
  }
}
```

------------------------------------------------------------------------

## Domain Enforcement (Server Side)

-   Only one active Org record.
-   default_escalation_manager_id must reference active employee.
-   Visual updates increment visual_version.
-   No hard delete permitted.
-   Org cannot be archived if active teams exist.

------------------------------------------------------------------------

## Architectural Guardrails

-   Controllers remain thin.
-   Application layer handles commands.
-   Domain layer enforces invariants.
-   No business logic in UI layer.
-   Escalation resolution must be deterministic.

------------------------------------------------------------------------

## Escalation Resolution Order

1.  Team escalation_team_id (if defined)
2.  Parent Team escalation
3.  Org default_escalation_team_id

Escalation resolution must never be handled in controller logic. It must
live in Domain services.
