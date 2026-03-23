# PET Documentation Location Index — 17 March 2026
Status: AUTHORITATIVE INDEX
Purpose: Single navigation map for active canonical specification locations and historical/superseded source files.
Scope: Documentation placement and supersession mapping only. No business rules are defined in this file.

## Usage Rule
- Use the `docs/` canonical paths listed under "Active Canonical Locations" for implementation decisions.
- The legacy staging directory used in earlier migration waves has been retired after migration completion on 2026-03-19.
- Keep superseded files only when explicitly marked for traceability in canonical sections.

## Active Canonical Locations
- Staff setup journey and staff-focused operational surfaces:
  - Active: `docs/06_features/staff_setup_journey.md`
  - Related: `docs/06_features/my_work_staff_surface.md`
  - Related: `docs/06_features/my_profile_staff_experience.md`
- Escalation completion spec:
  - Active: `docs/22_escalation_and_risk/PET_Escalation_Completion_Corrected_v2.md`
- Work orchestration completion spec:
  - Active: `docs/36_work_orchestration/PET_Work_Orchestration_Assignment_Routing_Queue_Visibility_v2.md`
- Advisory outputs and report generation completion spec:
  - Active: `docs/25_advisory_layer/PET_Advisory_Layer_Outputs_And_Report_Generation_v1.md`
- Dashboard composition and manager summary completion spec:
  - Active: `docs/10_dashboards/PET_Dashboard_Composition_And_Manager_Summary_Surfaces_v1.md`
- Performance benchmark surface spec:
  - Active: `docs/10_dashboards/performance_benchmark_surface.md`
- People resilience outputs completion spec:
  - Active: `docs/23_people_resilience/PET_People_Resilience_Outputs_v1.md`
- Support/helpdesk operational completion spec:
  - Active: `docs/31_support_helpdesk/PET_Support_Helpdesk_Operational_Completion_v1.md`
- Pulseway flags and billing export gap-closure spec:
  - Active: `docs/17_integrations/PET_GapClosure_Pulseway_Flags_BillingExport_v1.md`
- Implementation status (13 March 2026 session record):
  - Active record location: `docs/15_implementation_blueprint/PET_Implementation_Status_2026_03_13.md`
- Authoritative demo data pack and amendments:
  - Active: `docs/16_demo/PET_demo_data_pack_authoritative_v2_complete.md`

## Status Addenda
- 2026-03-23 addendum:
  - `docs/15_implementation_blueprint/PET_Implementation_Status_2026_03_23_Staff_Journey_Seed_Hardening_Addendum.md`
- 2026-03-19 addendum:
  - `docs/15_implementation_blueprint/PET_Implementation_Status_2026_03_19_TimeEntries_Admin_Surface_Addendum.md`
- 2026-03-17 remediation addendum:
  - `docs/15_implementation_blueprint/PET_Implementation_Status_2026_03_17_Remediation_Addendum.md`
- 2026-03-17 baseline stabilization addendum:
  - `docs/15_implementation_blueprint/PET_Implementation_Status_2026_03_17_Addendum.md`
- Rule:
  - Add new dated addenda for status changes.
  - Do not rewrite or delete prior dated status records.

## Explicitly Superseded Historical Files
- Staged migration files retired on 2026-03-23 after canonical placement:
  - `docs/06_features/staff_setup_journey.md`
  - `docs/10_dashboards/performance_benchmark_surface.md`
- `docs/22_escalation_and_risk/PET_Escalation_Engine_Completion_v1.md`
  - Status: SUPERSEDED
  - Superseded by: `docs/22_escalation_and_risk/PET_Escalation_Completion_Corrected_v2.md`
- `docs/36_work_orchestration/PET_Work_Orchestration_Assignment_Routing_Queue_Visibility_v1.md`
  - Status: SUPERSEDED
  - Superseded by: `docs/36_work_orchestration/PET_Work_Orchestration_Assignment_Routing_Queue_Visibility_v2.md`

## Maintenance Rule
When promoting additional migration-wave files:
- add canonical destination entry to this index
- apply explicit supersession marker if replaced
- append a dated implementation status addendum if operationally relevant
