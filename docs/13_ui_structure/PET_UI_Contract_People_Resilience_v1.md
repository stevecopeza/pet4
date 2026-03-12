# PET UI Contract --- People Resilience v1.0

Date: 2026-02-26

## Admin Pages

### Capability Requirements Manager (admin)

-   Create/edit requirement
-   Enable/disable
-   Set minimum_people and importance
-   Assign to team/department/role

### Resilience Dashboard (manager/admin)

-   KPI cards: SPOF count, critical SPOF count, coverage %
-   SPOF list with filters
-   "Run Analysis" button (manual, explicit)

## Shortcodes

-   \[pet_resilience_team\] (team coverage + SPOFs)
-   \[pet_resilience_wallboard\] (top SPOFs; read-only)

## Acceptance Criteria

-   SPOFs visible, filterable
-   Manual analysis is explicit and role-gated
