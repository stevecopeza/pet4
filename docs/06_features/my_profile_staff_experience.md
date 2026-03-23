# My Profile Staff Experience

STATUS: IMPLEMENTED  
OWNING LAYER: UI / Features  
SURFACE: Admin page `pet-my-profile`

## Purpose

`My Profile` provides a staff-centered operational identity surface that combines:

- personal identity
- role/team context
- capability visibility
- lightweight availability state
- responsibility and recent operational context

It is intentionally demo-focused and does not implement a full HR platform.

## Page Structure

Rendered in this order:

1. Profile Header
2. Identity & Role
3. Teams / Capabilities
4. Availability / Work Pattern
5. Responsibilities & Current Work
6. Recent Activity / Context

## Persistence Model

### Editable and persisted

- Identity fields (`firstName`, `lastName`, `email`)
- Team membership (`teamIds`)
- Availability context (stored in employee `malleableData`):
  - `availability_state`
  - `availability_pattern`
  - `next_available_note`
  - `location_note`
- Primary role context via assignment command path:
  - ends existing active assignment (if changed)
  - creates new assignment for selected role
- Capability addition:
  - employee skill ratings (`/employees/{id}/skills`)
  - employee certifications (`/employees/{id}/certifications`)

### Visible, read-only context

- Work responsibility summaries
- Recent activity timeline snippets

## Data Sources

Existing endpoints only:

- `/employees`
- `/teams`
- `/roles`
- `/assignments?employee_id=...`
- `/tickets`
- `/work/my-items` with fallback `/work-items?assigned_user_id=...`
- `/projects`
- `/activity?limit=...`
- `/skills`, `/employees/{id}/skills`
- `/certifications`, `/employees/{id}/certifications`

No new domain logic or state machines are introduced.

## Operational Linkage

Responsibilities section links directly into existing workspaces:

- ticket responsibilities → Support (`pet-support#ticket=<id>`)
- project responsibilities → Delivery (`pet-delivery#project=<id>`)

Header provides direct navigation to `My Work`.

## Non-goals

- no calendar/scheduling engine
- no permission model redesign
- no broad configuration framework

## Visual Verification + Finishing Pass (March 2026)

Focused polish was applied to improve clarity without introducing new domain behavior:

- strengthened profile hero hierarchy (identity prominence + availability cue)
- normalized section headers and spacing across all profile sections
- improved edit-mode affordance with bordered edit containers
- improved chip readability for capabilities and certifications
- improved responsibility stats and linked responsibility card readability
- added explicit inline post-save visibility cue for availability updates

### Evidence capture note

In the currently authenticated E2E environment, `pet-my-profile` renders a WordPress placeholder (`Coming Soon`) instead of the React profile workspace.  
This blocks capture of true My Profile overview/edit/post-save visuals in that environment despite successful code-level implementation.

## Related Documentation
- Staff setup journey: `06_features/staff_setup_journey.md`
- My Work surface: `06_features/my_work_staff_surface.md`
- Screen contracts: `13_ui_structure/02_screen_level_contracts.md`
