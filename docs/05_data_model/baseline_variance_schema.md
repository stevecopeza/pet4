# Baseline & Variance -- Schema

## project_baselines

-   id (UUID, PK)
-   project_id (UUID, FK)
-   version_number (int)
-   source_contract_id (UUID, FK)
-   internal_cost_ceiling (decimal 14,2)
-   created_at (datetime)

## variance_orders

-   id (UUID, PK)
-   project_id (UUID, FK)
-   amount (decimal 14,2)
-   reason (text)
-   approved_by (UUID)

## Rules

-   Re-baseline explicit only.
-   Variance does not alter contract price.
