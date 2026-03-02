# Certifications Domain Specification

## Entity: Certification

### Mandatory Fields

-   certification_name
-   issuing_body
-   status (active | archived)

### Structured Fields

-   description
-   expiry_period_months
-   renewal_requirement
-   verification_required (boolean)
-   certification_level
-   mandatory_flag_default (suggestion for role linkage)

## PersonCertification (Instance)

### Fields

-   person_id
-   certification_id
-   obtained_date
-   expiry_date (if applicable)
-   compliance_status (valid | expired | missing)
-   evidence_id (multiple allowed)

## Compliance Rules

-   Mandatory certifications (defined in Role) block role assignment if missing.
-   Expiry triggers compliance event + notifies manager and staff.
