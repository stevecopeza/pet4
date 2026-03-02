# Work Domain – Overview

## Purpose

The Work domain defines structured organisational capability inside PET.
It governs:
- Role definitions (versioned)
- Skills framework
- Certification governance (including expiry and compliance)
- Person-to-role assignments
- Dual skill rating model
- KPI snapshot inheritance
- Hiring profile generation

## Architectural Principles

-   Roles are versioned and immutable once published.
-   Role KPIs are snapshotted at assignment time.
-   Corrections are additive; historical truth is preserved.
-   Remuneration lives on PersonRoleAssignment.
-   Certifications may be mandatory and may expire.
-   Skills use dual rating (Self + Manager).

## Implementation

For concrete database schemas and API specifications, refer to:
[Implementation Guide](docs_26_work_domain_14_implementation_guide.md)

---

# Capability Framework

## Entity: Capability

### Mandatory Fields

-   capability_name
-   status (active | archived)

### Structured Fields

-   description
-   parent_capability_id (optional)

## Relationship

Capability └── Skill (many)

## Purpose

-   Enables macro-level gap reporting
-   Enables strategic capability planning
-   Prevents skill sprawl
