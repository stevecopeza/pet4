# Quote Entity

## Mandatory Fields

-   customer_id
-   version_number
-   status
-   created_by
-   created_date

## Structured Fields

-   cover_content
-   solution_description
-   total_sell_value
-   total_internal_cost
-   total_margin
-   lead_id (nullable FK → leads) — set when quote is created via Lead conversion; NULL for direct quote creation

## Rules

-   Every material change creates new version.
-   Accepted quote immutable.
-   `lead_id` is nullable — quotes may be created directly for a Customer without a Lead.
-   A quote created via lead conversion inherits `customerId` and `subject` from the source Lead.
