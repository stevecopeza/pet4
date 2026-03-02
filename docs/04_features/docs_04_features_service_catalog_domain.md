# Service Catalog Domain

## Entity: ServiceCatalogItem

### Mandatory Fields

-   name
-   department_reference
-   base_internal_rate
-   recommended_sell_rate
-   status

### Structured Fields

-   description
-   skill_level_reference
-   cost_category
-   default_margin_percentage

## Rules

-   Quotes snapshot economic fields at time of use.
-   Sell rate below recommended triggers approval rule.
