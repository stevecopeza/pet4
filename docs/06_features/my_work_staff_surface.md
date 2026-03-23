# My Work Staff Operational Surface

STATUS: IMPLEMENTED
OWNING LAYER: UI / Features
SCOPE: Admin Surface (`pet-my-work`)

## Purpose
`My Work` is a staff-centric operational console that answers:
- what needs attention now
- what is actively in progress
- what is stable and mostly monitoring

It prioritizes high-signal routing into existing PET workspaces.

## Surface Structure
Top-to-bottom order:
1. Header (`My Work`)
2. Attention strip (max 4 summary signals)
3. Needs Attention
4. In Progress
5. Stable / Monitoring

This is intentionally not a dashboard table.

## Data Composition
Uses existing read endpoints:
- `/tickets`
- `/projects`
- `/work/my-items`

No new domain model/state machine is introduced.

## Classification Model
### Workload-first mode
When `/work/my-items` is available, grouping is primarily derived from:
- source type (`ticket` / `project`)
- workload priority score
- workload signal severities
- SLA time remaining signal

### Fallback mode
When workload items are unavailable, the surface falls back to assigned-ticket composition and ticket/project health-state grouping.

## Item Rendering Contract
Each item displays:
- title
- context (ticket/project/customer reference)
- type badge
- status and risk labels

Section views intentionally cap visible rows and display overflow counters.

## Interaction Contract
No new detail screens are created.

Routing:
- ticket click → Support (`pet-support#ticket=<id>`)
- project click → Delivery (`pet-delivery#project=<id>`)

## Scope Constraints
- UI composition and routing only
- no new settings/filter frameworks
- no direct mutation actions from this surface

## Related Documentation
- Staff profile surface: `06_features/my_profile_staff_experience.md`
- Staff setup journey: `06_features/staff_setup_journey.md`
- Screen contracts: `13_ui_structure/02_screen_level_contracts.md`
