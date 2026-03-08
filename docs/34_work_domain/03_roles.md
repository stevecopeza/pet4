# Roles Domain Specification

## Entity: Role (Versioned)

### Lifecycle

Draft → Published → Deprecated → Archived

### Mandatory Fields

-   role_name
-   role_version
-   status (draft | published | deprecated | archived)
-   level (Junior, Intermediate, Senior, etc.)
-   success_criteria
-   review_frequency

### Structured Fields

-   role_description
-   reporting_line_expectation
-   expected_utilisation_percentage
-   probation_expectations
-   compensation_band_reference
-   approval_authority

### Relationships

-   Required Skills (with minimum level + weight)
-   Optional Skills
-   Required Certifications (flag mandatory)
-   Role KPIs (template only; snapshot at assignment)

## Versioning Rules

-   Once published, a Role version cannot be edited.
-   New version must be created for changes.
-   Existing assignments remain pinned to original version.
