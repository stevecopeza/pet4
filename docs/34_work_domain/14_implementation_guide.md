# Work Domain – Implementation Guide

## Purpose

This document translates the conceptual entities defined in the Work Domain into concrete **Database Schemas** and **API Specifications** compliant with PET's architecture.

## Database Schema

### Table Naming Convention
All tables are prefixed with `pet_` (via `$wpdb->prefix`).

### 1. Proficiency & Capabilities

#### `pet_proficiency_levels`
Defines the global scale (e.g., 1-5).
- `id` (BIGINT, PK)
- `level_number` (INT)
- `name` (VARCHAR)
- `definition` (TEXT)
- `created_at` (DATETIME)

#### `pet_capabilities`
- `id` (BIGINT, PK)
- `name` (VARCHAR)
- `description` (TEXT)
- `parent_id` (BIGINT, NULL)
- `status` (VARCHAR: 'active', 'archived')
- `created_at` (DATETIME)

#### `pet_skills`
- `id` (BIGINT, PK)
- `capability_id` (BIGINT, FK -> pet_capabilities)
- `name` (VARCHAR)
- `description` (TEXT)
- `status` (VARCHAR: 'active', 'archived')
- `created_at` (DATETIME)

### 2. Roles (Versioned)

#### `pet_roles`
- `id` (BIGINT, PK)
- `name` (VARCHAR)
- `version` (INT)
- `status` (VARCHAR: 'draft', 'published', 'deprecated', 'archived')
- `level` (VARCHAR)
- `description` (TEXT)
- `success_criteria` (TEXT)
- `created_at` (DATETIME)
- `published_at` (DATETIME, NULL)

#### `pet_role_skills` (Join Table)
- `role_id` (BIGINT, FK -> pet_roles)
- `skill_id` (BIGINT, FK -> pet_skills)
- `min_proficiency_level` (INT)
- `importance_weight` (INT)
- `primary key` (role_id, skill_id)

### 3. Assignments & People

#### `pet_person_role_assignments`
- `id` (BIGINT, PK)
- `employee_id` (BIGINT, FK -> pet_employees.id)
- `role_id` (BIGINT, FK -> pet_roles.id)
- `start_date` (DATE)
- `end_date` (DATE, NULL)
- `allocation_pct` (INT)
- `status` (VARCHAR: 'active', 'completed')
- `created_at` (DATETIME)

#### `pet_person_skills` (Ratings)
- `id` (BIGINT, PK)
- `employee_id` (BIGINT, FK -> pet_employees.id)
- `skill_id` (BIGINT, FK -> pet_skills.id)
- `review_cycle_id` (BIGINT, NULL)
- `self_rating` (INT)
- `manager_rating` (INT)
- `effective_date` (DATE)
- `created_at` (DATETIME)

### 4. Governance

#### `pet_certifications`
- `id` (BIGINT, PK)
- `name` (VARCHAR)
- `issuing_body` (VARCHAR)
- `expiry_months` (INT)
- `status` (VARCHAR)

#### `pet_person_certifications`
- `id` (BIGINT, PK)
- `employee_id` (BIGINT, FK -> pet_employees.id)
- `certification_id` (BIGINT, FK -> pet_certifications.id)
- `obtained_date` (DATE)
- `expiry_date` (DATE, NULL)
- `evidence_url` (VARCHAR, NULL)
- `status` (VARCHAR: 'valid', 'expired')

## API Contract Extensions

### Roles
- `POST /pet/v1/roles/{id}/publish`
  - Action: Locks version, creates immutable snapshot.
  - Returns: 200 OK.

### Skills
- `POST /pet/v1/employees/{id}/skills`
  - Body: `{ skill_id: 1, self_rating: 3, manager_rating: 4 }`
  - Action: Records new rating entry.

### Certifications
- `POST /pet/v1/employees/{id}/certifications`
  - Body: `{ certification_id: 1, obtained_date: '2023-01-01', evidence: 'url...' }`
  - Action: Records certification.

## Integration Points

- **Employee**: The `pet_employees` table is the root aggregate. All "Person" references in this domain map strictly to `pet_employees.id`.
- **WordPress Users**: No direct link to `wp_users` in this domain; strictly via `pet_employees`.
