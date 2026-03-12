# Feature Specification -- Teams

## Purpose

Teams are operationally binding governance objects.

## Functional Responsibilities

-   SLA routing
-   Escalation handling
-   Structural visibility
-   Project ownership defaults
-   Time approval hierarchy
-   Dashboard rollups

## List View

-   Hierarchical tree display (expand/collapse)
-   Status indicator
-   Visual icon rendering

## Add/Edit Fields

-   Name (required)
-   Parent Team
-   Manager (Dropdown of active employees)
-   Escalation Manager (Dropdown of active employees)
-   Status
-   Visual Identifier
-   Team Members (Multi-select)

## Team Membership

-   Teams support many-to-many relationships with Employees.
-   An Employee can be a member of multiple teams.
-   Membership is managed via the Team Create/Edit form or the Employee Create/Edit form.
-   "Members" are distinct from the "Manager" and "Escalation Manager" roles.

## Validation Rules

-   Unique name within parent
-   No circular hierarchy
-   No circular escalation
-   Cannot archive if children exist

## Escalation Semantics

-   If escalation_manager_id exists → escalate there.
-   If null → escalate to parent manager.
-   Root escalation → system default governance manager.

Escalation is structural and deterministic.
