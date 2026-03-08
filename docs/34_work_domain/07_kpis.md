# KPI Domain Specification

## KPI Weighting Model

### Role KPI Template (Definition)

#### Fields

-   kpi_template_id
-   weight_percentage
-   target_value
-   measurement_frequency

#### Rules

-   Weight required
-   Total weight per role not required to equal 100%
-   Weight used for performance scoring only

## Role KPI Linkage (Assignment)

### Model

-   Role defines KPI templates.
-   On assignment, KPI instances are created for Person.
-   Instances are immutable except via compensating adjustment.

### Change Handling

-   New Role version required to change KPI template.
-   Historical KPI performance remains linked to original snapshot.
