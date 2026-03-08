# Payment Plan -- Schema

## quote_payment_plan_rules

-   id (UUID, PK)
-   quote_id (UUID, FK)
-   trigger_type (enum)
-   configuration_json (json)

## contract_payment_schedule

-   id (UUID, PK)
-   contract_id (UUID, FK)
-   due_date (date)
-   amount (decimal 14,2)
-   trigger_reference (varchar 255)
-   status (enum: pending, invoiced, paid, overdue)

## Rules

-   Canonical schedule generated once and snapshotted.
