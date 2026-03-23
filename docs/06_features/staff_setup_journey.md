# Staff Setup Journey
STATUS: IMPLEMENTED
OWNING LAYER: UI / Features
SURFACE: Admin page `pet-people` (People tab)
FEATURE FLAG: `pet_staff_setup_journey_enabled`

## Purpose
The Staff Setup Journey replaces fragmented staff onboarding/editing with a guided, readiness-oriented flow that keeps managers focused on the minimum required setup path for each employee.

## Readiness Contract (Derived at Runtime)
Readiness is derived in UI composition logic from existing employee/team/assignment state and is not persisted as a dedicated field.

States:
- `incomplete`: identity incomplete OR org placement incomplete
- `partial`: identity + org placement complete, but no active role assignment
- `ready`: identity + org placement + at least one active role assignment

Derived fields shown in list view:
- setup status badge (`incomplete`, `partial`, `ready`)
- next-step hint
- next-step action (`Open Setup Journey`)

## Org Placement Validity Rule
Standard valid org placement:
- manager assigned
- one or more team assignments

Top-of-structure exception (still valid):
- no manager
- one or more team assignments
- one or more direct reports

Invalid:
- no manager and no teams
- manager with no teams

## Journey Step Model
Ordered steps:
1. Identity (required)
2. Org Placement (required)
3. Role Assignment (required for `ready`)
4. Capabilities (optional)
5. Management Context (optional)

Step navigation supports:
- direct step selection
- previous/next controls
- per-step status labels
- progress meter (`x/5`, including required-step completion)

## Setup Focus Mode
When journey mode is open for an employee:
- setup panels are emphasized
- non-setup operational panels are suppressed (`Capacity & Utilization`, `Leave Requests`)

When journey mode closes:
- standard People view panels are restored

## Staff List Behaviour
When feature flag is enabled:
- explicit sort selector: `Readiness` (default) or `Name`
- readiness sort order: `incomplete` → `partial` → `ready`
- tie-breakers: active role count, then display name

When feature flag is disabled:
- standard employee edit behavior is used (non-journey form flow)

## Implementation References
- UI entry and orchestration: `src/UI/Admin/components/Employees.tsx`
- feature-flag resolution: `src/Application/System/Service/FeatureFlagService.php`
- feature-flag exposure to admin app: `src/UI/Admin/AdminPageRegistry.php`
- journey tests: `src/UI/Admin/__tests__/Employees.staff-setup-journey.test.tsx`

## Operational Validation (2026-03-23)
Validated in seeded demo data:
- readiness distribution preserved after repeated seed runs:
  - `ready=5`
  - `partial=2`
  - `incomplete=1`

This confirms:
- deterministic derived readiness behavior remains stable
- idempotent reseed does not collapse persona coverage

## Non-goals
- no schema changes for setup state
- no backend state machine for journey progression
- no hard gating that blocks all editing until prior steps are complete

## Related Documentation
- People module overview: `06_features/people_management.md`
- Staff profile surface: `06_features/my_profile_staff_experience.md`
- Staff work surface: `06_features/my_work_staff_surface.md`
- Screen contracts: `13_ui_structure/02_screen_level_contracts.md`

