# Evidence Entity Specification

## Entity: Evidence

### Mandatory Fields

-   evidence_type (certification | assessment | performance)
-   uploaded_by
-   uploaded_date

### Structured Fields

-   file_reference (multiple allowed)
-   description
-   related_entity_type
-   related_entity_id

## Rules

-   Immutable once attached
-   Versioned via additive upload
