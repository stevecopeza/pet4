# Data Model -- Teams

## Table: pet_teams

  Column               Type                     Notes
  -------------------- ------------------------ -----------------------------
  id                   bigint PK                Immutable
  name                 varchar                  Required, unique per parent
  parent_team_id       bigint nullable          Self-reference
  manager_id              bigint nullable          FK to pet_employees
  escalation_manager_id   bigint nullable          FK to pet_employees
  status                  enum(active, archived)   No deletion
  visual_type          enum(system, upload)     Core
  visual_ref           varchar                  Core
  visual_version       int                      Core
  visual_updated_at    datetime                 
  created_at           datetime                 Immutable
  archived_at          datetime nullable        

## Constraints

-   No circular parent hierarchy.
-   Cannot escalate to archived manager.
-   Manager must be active employee.
-   Cannot archive team with active child teams.

## Table: pet_team_members

  Column        Type
  ------------- --------------------
  id            bigint PK
  team_id       bigint FK
  employee_id   bigint FK
  role          enum(member, lead)
  assigned_at   datetime
  removed_at    datetime nullable

## Rules

-   Supports multi-team membership.
-   Historical assignments retained.
-   No hard deletes.
