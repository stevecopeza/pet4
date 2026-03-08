# Visual Identity Policy

## Purpose

Defines a consistent, versioned, and governance-aligned visual identity
model for PET entities.

## Scope

Applies to: - Teams - People - Companies - Sites - SLAs - Any future
visual-capable entity

## Design Principles

-   Visual identity is representational only.
-   Visual identity must never influence domain logic.
-   Visual identity must be versioned.
-   Historical views must render using the version active at the time of
    the event.

## Standard Fields

  Field               Description
  ------------------- -------------------------------
  visual_type         system, upload
  visual_ref          system key or asset reference
  visual_version      integer, increments on change
  visual_updated_at   timestamp

## Storage Policy

Visual assets must be stored in PET-owned storage using the pet_assets
table. WordPress Media Library is not authoritative.

## Versioning Rules

-   Updating a visual increments visual_version.
-   Historical domain events must reference the visual_version active at
    time of event.
-   No retroactive modification of historical visual references.
