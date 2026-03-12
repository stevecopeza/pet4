# Person Work Assignments

## Entity: PersonRoleAssignment

### Mandatory Fields

-   person_id
-   role_version_id
-   effective_date
-   allocation_percentage

### Structured Fields

-   role_type (primary | secondary | temporary | acting)
-   remuneration_amount (discretionary)
-   remuneration_type (salary | allowance | bonus_component)
-   end_date
-   assignment_status (active | completed | revoked)

## Allocation Rule

-   No enforced 100% total requirement
-   Allocation can exceed or be less than 100% (supports part-time & auxiliary roles)

## KPI Snapshot Rule

-   Role KPIs are instantiated as Person KPIs at assignment time.
-   Subsequent role changes do not affect existing assignments.
-   Instances immutable except compensating adjustment.
