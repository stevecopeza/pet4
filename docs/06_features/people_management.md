# PET – People Management

## Overview
People Management is surfaced at `PET → Staff` and governs employee records, org placement, and staff-readiness workflows.

Primary surface:
- `pet-people` (`Employees.tsx`)

Internal tabs:
- Org
- Teams
- People

## Core Capabilities

### 1) Employee List and Operational Filters
- employee list with identity, status, org context, and staffing signals
- status/manager/search filtering
- bulk selection and archive operations
- quick presets (active, no manager, archived)

### 2) Employee Record Create/Edit
Editable fields:
- identity (`firstName`, `lastName`, `email`)
- org placement (`managerId`, `teamIds`)
- employment/status fields (including archival behavior)
- schema-driven malleable fields

### 3) Org and Teams Navigation
- org tab visualizes team hierarchy and manager/member relationships
- team/member clicks route into People edit flow

### 4) Archival Model
- soft archival (not hard delete)
- historical links remain intact for time/commercial/support references

---

## Staff Setup Journey (Feature-Gated)
Feature flag:
- `pet_staff_setup_journey_enabled`

When enabled, the People tab adds readiness-driven setup guidance:
- setup status (`incomplete`, `partial`, `ready`)
- next-step hints and `Open Setup Journey` action
- explicit sort mode selector (`Readiness` or `Name`)

Journey steps:
1. Identity
2. Org Placement
3. Role Assignment
4. Capabilities (optional)
5. Management Context (optional)

Readiness is runtime-derived from existing employee/team/assignment state (no persisted setup-status field).

See canonical spec:
- `06_features/staff_setup_journey.md`

---

## Related Staff Surfaces
- My Work: `06_features/my_work_staff_surface.md`
- My Profile: `06_features/my_profile_staff_experience.md`

---

## Technical Implementation References
- UI: `src/UI/Admin/components/Employees.tsx`
- Form: `src/UI/Admin/components/EmployeeForm.tsx`
- API:
  - `GET/POST /pet/v1/employees`
  - `PUT/DELETE /pet/v1/employees/{id}`
  - `GET /pet/v1/assignments` (role readiness derivation support)
- Flag exposure: `src/UI/Admin/AdminPageRegistry.php`

**Authority**: Informational
