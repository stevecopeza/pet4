# PET Demo Data Pack (Authoritative – Complete v2)

Date: 2026-02-14  
Status: PROPOSED (Becomes authoritative once accepted)  
Replaces: PET_demo_data_pack_authoritative.md

This document defines the COMPLETE, deterministic, realistic demo dataset for PET,
including:

- Identity
- Teams
- Calendars
- Capability model (roles, skills, certifications, KPIs)
- Commercial (leads, multiple quote types, contracts, baselines)
- Delivery (projects, tasks)
- Support (full SLA state coverage)
- Work orchestration
- Time entries
- Leave (approved / pending / rejected)
- Knowledge + feed + activity
- QuickBooks-first billing (exports + shadow invoices/payments)
- Event backbone expectations
- Seed + purge mechanics

This is the single authoritative reference for demo activation.

====================================================================
SECTION 1 — SEED MECHANICS (BINDING)
====================================================================

Seed profile: demo_full  
All seeded rows must include:

If table contains malleable_data JSON:
- seed_run_id
- seed_profile = "demo_full"
- seeded_at
- touched_at (nullable)
- touched_by_employee_id (nullable)

If metadata_json exists:
- same keys under metadata_json

TRAE may adjust ONLY timestamps to keep data recent:
- created_at, updated_at, opened_at, closed_at, submitted_at, accepted_at,
  response_due_at, resolution_due_at

All relationships and sample values must remain as specified.

====================================================================
SECTION 2 — IDENTITY
====================================================================

Employees (6)
1. Steve Admin (Exec)
2. Mia Manager (Delivery Manager)
3. Liam Lead Tech (Implementation)
4. Ava Consultant (Implementation)
5. Noah Support (Support Tech)
6. Zoe Finance (Finance Coordinator)

Manager structure:
- Steve → Mia → (Liam, Ava, Noah)
- Steve → Zoe

Customers (2)
1. RPM Resources (Pty) Ltd
2. Acme Manufacturing SA (Pty) Ltd

Sites:
- RPM Cape Town
- RPM Johannesburg
- Acme Stellenbosch

Contacts (4 total):
- Priya Patel (RPM Finance)
- John Mokoena (RPM Ops)
- Sarah Jacobs (Acme IT)
- David Naidoo (Acme GM)

Affiliations set correctly to site + customer.

====================================================================
SECTION 3 — TEAMS
====================================================================

Teams:
1. Executive (Steve)
2. Delivery (Mia)
3. Support (Noah)
4. Delivery Engineering (Liam, child of Delivery)

Hierarchy:
- Executive (root)
- Delivery (child of Executive)
- Support (child of Executive)
- Delivery Engineering (child of Delivery)

Team memberships assigned realistically.

====================================================================
SECTION 4 — CALENDAR
====================================================================

Default Calendar:
- Africa/Johannesburg
- Mon–Fri 08:30–17:00
- After-hours 17:00–20:00 (1.5 multiplier)

Holidays seeded (recurring):
- 01 Jan
- 27 Apr

====================================================================
SECTION 5 — CAPABILITY MODEL
====================================================================

Proficiency Levels 1–5

Capabilities:
- Implementation
- Support
- Finance Ops

Skills mapped to capabilities.

Roles:
- Project Manager
- Implementation Consultant
- Support Technician
- Finance Coordinator

Role skills configured with weight + minimum proficiency.

Certifications:
- SYSPRO Consultant Certification
- ITIL Foundation

Person role assignments:
- Mia → PM
- Liam/Ava → Implementation Consultant
- Noah → Support Technician
- Zoe → Finance Coordinator

Person skills + certifications seeded realistically.

KPIs seeded for last completed month:
- Noah (First Response Time)
- Liam (Utilisation)
- Zoe (Invoice Turnaround)

====================================================================
SECTION 6 — LEAVE (FULL COVERAGE)
====================================================================

Leave Types:
- Annual Leave
- Sick Leave

Leave Requests:

1. Ava — Annual Leave
   24–28 Feb 2026
   status: approved
   approved_by: Mia

2. Liam — Annual Leave
   10–14 Mar 2026
   status: submitted (pending)

3. Noah — Annual Leave
   20–21 Feb 2026
   status: rejected
   approved_by: Mia

Capacity impact must reflect Ava’s approved leave.

====================================================================
SECTION 7 — CATALOG
====================================================================

6 catalog items seeded:
- Consulting Hour
- Support Hour
- Connector Licence
- Travel
- QBR Session
- Onboarding Pack

====================================================================
SECTION 8 — COMMERCIAL (MULTIPLE QUOTE STRUCTURES)
====================================================================

Lead 1: RPM SYSPRO Upgrade Phase 2  
Lead 2: Acme Support Retainer

Quotes:

Q1 — Composite Quote (Accepted)
- Milestones + tasks
- Catalog items
- Recurring service (12 months)
- Payment schedule
- accepted_at populated

Q2 — Milestone-Only (Draft)
- 1 milestone
- 3 tasks
- no recurring
- no catalog

Q3 — Recurring-Only (Draft)
- Support Retainer 10h/month
- 6-month term

Q4 — Catalog-Only (Accepted)
- 2 product items
- no milestones
- accepted

====================================================================
SECTION 9 — CONTRACT + BASELINE
====================================================================

Contract from Q1 (active)
Baseline snapshot created
Baseline components:
- Discovery milestone
- Migration milestone
- Recurring support

====================================================================
SECTION 10 — DELIVERY
====================================================================

Project:
"RPM SYSPRO Upgrade Phase 2"

8 tasks mapped to quote tasks.
Mix of completed + in-progress.

====================================================================
SECTION 11 — SUPPORT (FULL SLA MATRIX)
====================================================================

SLA:
RPM Standard SLA
- response: 60 min
- resolution: 480 min

Tickets (7 total):

1. High priority, in_progress, near breach, escalated level 1
2. Medium priority, breached, escalation level 2
3. Closed within SLA
4. Project-linked ticket
5. Recurring support context
6. Pending_customer, SLA paused
7. Critical, unassigned in queue

Each has:
- sla_snapshot_id
- SLA clock row
- response_due_at / resolution_due_at
- escalation stages where relevant

====================================================================
SECTION 12 — WORK ORCHESTRATION
====================================================================

Work items created for:
- all active tickets
- selected project tasks

Include:
- assigned
- unassigned
- escalated
- high priority queue scenario

Department queues populated accordingly.

====================================================================
SECTION 13 — TIME ENTRIES
====================================================================

20 entries across 3 employees:
- draft
- submitted
- approved
- 1 rejected/corrected entry
- linked to tickets and project tasks

====================================================================
SECTION 14 — KNOWLEDGE
====================================================================

6 articles seeded (published).

====================================================================
SECTION 15 — FEED + ANNOUNCEMENTS
====================================================================

10 feed events:
- SLA warning
- Breach logged
- Billing export queued
- QB payment synced
- Leave approved
- Milestone completed

2 announcements:
- Ack required
- Informational

Acknowledgements seeded.

====================================================================
SECTION 16 — BILLING (QB-FIRST)
====================================================================

Billing Export:
- period: mid-Jan to end-Jan
- status: sent
- items: time entries + baseline component

QB Shadow Data:

Invoice 1:
- partial paid
- balance remaining

Invoice 2:
- fully paid (balance 0)

Invoice 3:
- overdue (due date past, balance > 0)

Payment 1:
- partial allocation

Payment 2:
- full allocation

External mappings seeded.

Integration runs seeded:
- push success
- pull success
- failed export scenario

Event stream contains:
- export created
- export queued
- qb invoice upserted
- qb payment upserted

====================================================================
SECTION 17 — PURGE RULES
====================================================================

Delete:
- seeded AND untouched

Archive:
- seeded AND touched (if archived_at supported)

Preserve (never delete):
- accepted quotes
- submitted time
- queued/sent billing exports
- approved leave
- event stream

====================================================================
SECTION 18 — VALIDATION REQUIREMENTS
====================================================================

After seed:
- All relationships valid
- No orphaned rows
- SLA clocks consistent
- Capacity reflects leave
- Billing export linked to QB invoice via mapping
- Work items linked to valid sources

After purge:
- Untouched seeded rows removed
- Immutable preserved
- Event stream intact

====================================================================

This dataset now fully supports:

- Commercial lifecycle demo
- Delivery execution demo
- SLA + escalation demo
- Work orchestration demo
- Capability + KPI demo
- Leave + capacity realism demo
- Finance visibility via QuickBooks demo
- Governance narrative
- Safe demo reset

End of Authoritative Demo Data Pack v2.

====================================================================
AMENDMENT v2.1 — Expanded Demo Data Volumes (2026-03-02)
====================================================================

Status: ADDITIVE AMENDMENT (no breaking changes to v2 structure)

The following sections have been expanded in the implemented seed to
provide richer demo coverage. All v2 relationships and invariants
remain intact.

SECTION 2 — IDENTITY (Expanded)

Employees (8, was 6):
1. Steve Admin (Exec)
2. Mia Manager (Delivery Manager)
3. Liam Lead Tech (Implementation)
4. Ava Consultant (Implementation)
5. Noah Support (Support Tech)
6. Zoe Finance (Finance Coordinator)
7. Ethan DevOps (NEW)
8. Isabella Analyst (NEW)

Customers (6, was 2):
1. RPM Resources (Pty) Ltd
2. Acme Manufacturing SA (Pty) Ltd
3. Nexus Startup Labs (NEW)
4. Government Digital Services (NEW)
5. Atlas Holdings (NEW — incomplete setup)
6. Bluewave Logistics (NEW — partial setup)

Sites (7, was 3):
- RPM Cape Town, RPM Johannesburg
- Acme Stellenbosch
- Nexus Cape Town (NEW)
- GDS Pretoria HQ (NEW), GDS Regional Office (NEW)
- Bluewave Durban Hub (NEW)

Contacts (9, was 4):
- Original 4 retained
- Tariq Hendricks (Nexus, NEW)
- Lisa van Wyk (Nexus, NEW)
- Thabo Dlamini (GDS, NEW)
- Megan Ross (RPM, NEW)
- Naledi Khoza (GDS, NEW)

Contact Variant Coverage (NEW):
- Customer-level contacts with no site affiliation
- Branch-bound contacts with a primary branch affiliation
- Multi-branch contacts with primary + secondary affiliations
- Mixed compatibility shape retained (at least one direct customer contact seeded without affiliation rows)

Customer Setup Stage Coverage (NEW):
- Incomplete: Atlas Holdings (0 branches, 0 contacts)
- Partially configured: Bluewave Logistics (1 branch, 0 contacts)
- Ready: RPM, Acme, Nexus, Government Digital Services (≥1 branch and ≥1 contact)

SECTION 3 — TEAMS (Expanded)

Teams (4, was 3):
- Executive (manager: Steve)
- Delivery (manager: Mia, parent: Executive)
- Support (manager: Noah, parent: Executive)
- Delivery Engineering (NEW; manager: Liam, parent: Delivery)

Membership coverage includes a manager and at least one member in Delivery Engineering for hierarchy/cascading demos.

SECTION 7 — CATALOG (Expanded)

14 catalog items (was 6). Original 6 retained, plus:
- Advanced Integration, Security Audit, Data Migration,
  Performance Tuning, Training Workshop, Emergency Support,
  Cloud Hosting, Compliance Review

SECTION 8 — COMMERCIAL (Expanded)

7 quotes (was 4). Original 4 retained, plus:
- Q5: Nexus Managed Services (recurring-heavy, draft)
- Q6: GDS Security Assessment (milestone-only, draft)
- Q7: Acme Emergency Hotfix (catalog-only, accepted)

SECTION 10 — DELIVERY (Expanded)

3 projects (was 1). Original retained, plus:
- Nexus Cloud Migration
- GDS Security Hardening

27 project tasks distributed across all 3 projects.

SECTION 11 — SUPPORT (Expanded)

13 tickets (was 7). Original 7 scenarios retained, plus:
- Additional priority/status variety across new customers
- Nexus and GDS tickets included

SECTION 13 — TIME ENTRIES (Expanded)

~30 entries distributed across 8+ tickets (was 20 entries on 1 ticket).
Distribution strategy:
- 4-5 entries on first 3 tickets
- 2-4 entries on next 5 tickets
- 0-1 entries on remaining tickets
- Employees rotated across tickets
- Realistic support work descriptions (30 varied descriptions)
- Duration variety: 15, 25, 30, 45, 60, 75, 90, 120, 150, 180 min
- ~67% billable
- Status distribution: ~45% draft, ~40% submitted, ~15% locked

SECTION 15 — FEED + ANNOUNCEMENTS (Expanded)

47 feed events (was 10). 18 conversations. 2 announcements retained.

SEED PIPELINE (Amendment)

New step added as first pipeline step:
- seed.featureFlags — seeds 10 feature flags, all enabled

New steps added after seed.feed:
- seed.conversations — ticket-context conversations
- seed.projectTasks — granular task data for projects

End of Amendment v2.1.

====================================================================
AMENDMENT v2.2 — Seed/Purge Hardening and Deterministic Rerun Validation (2026-03-23)
====================================================================

Status: ADDITIVE AMENDMENT (no breaking changes to v2 or v2.1 contracts)

This amendment records operational hardening applied to demo seed/purge mechanics and the validated outcomes from repeated seed runs on 2026-03-23.
TEAM TOPOLOGY HARDENING (IMPLEMENTED 2026-03-23)

1) Deterministic team structure reconciliation
- Team seeding now reconciles a managed topology on every run:
  - Executive (root)
  - Delivery (parent: Executive)
  - Support (parent: Executive)
  - Delivery Engineering (parent: Delivery)
- Existing rows are reused when present; missing managed teams are created.

2) Manager and escalation chain reconciliation
- Managed teams are now deterministically updated with:
  - `manager_id`
  - `escalation_manager_id`
  - `parent_team_id`
  - `archived_at = null`
- Escalation chain coverage now includes:
  - Delivery → escalation manager Steve
  - Support → escalation manager Mia
  - Delivery Engineering → escalation manager Mia

3) Membership coverage for hierarchy demos
- Team membership seeding now explicitly includes Delivery Engineering membership:
  - Liam = lead
  - Ethan = member
- This guarantees parent-child hierarchy and cascade behavior can be demonstrated consistently after repeated reseeds.

ORG UX AND VALIDATION CHECKLIST (IMPLEMENTATION CONTRACT)

The Org surface and seed validation are expected to enforce the following:

1) Canonical demo topology
- Executive (root)
- Delivery (child of Executive)
- Support (child of Executive)
- Delivery Engineering (child of Delivery)

2) Escalation visibility
- Org cards show both `Manager` and `Escalation Manager` when available.

3) Manager/member clarity
- Team member chips for the current team visually tag the manager, so manager presence in the member list is explicit instead of appearing as duplicate data.

4) Multi-team membership clarity
- Org member chips indicate additional team memberships (excluding the current team) to make matrix staffing explicit in demos.

5) Hierarchy readability affordances
- Team nodes provide collapse/expand behavior for child teams to keep deep hierarchies readable.

6) Deterministic reseed expectations
- Managed teams are reconciled on each seed run.
- Parent/manager/escalation links are rewritten deterministically for managed teams.

7) Post-seed org invariants
- Validation checks must evaluate at least:
  - exactly one root among managed teams (`Executive`)
  - managed teams not orphaned
  - no cycles in managed parent hierarchy
  - each managed team has a manager
  - `Delivery Engineering` parent resolves to `Delivery`

ORG VISUAL CLARITY PASS (IMPLEMENTED 2026-03-23)

The Staff → Org tab now implements a visual clarity pass for hierarchy legibility and role scanning.

1) Team header scan metadata
- Team cards show compact summary metadata (member count, child team count) in the header.

2) Stronger hierarchy cues
- Child trees render with a persistent left rail and depth spacing for clearer parent/child recognition.

3) Expand/collapse navigation controls
- Team-level expand/collapse controls remain available on nodes with children.
- Org-level quick actions are provided for expand-all / collapse-all workflows.

4) Role chip clarity in member list
- Member chips show explicit role badges where available:
  - `Manager` (if member is the team manager)
  - `Lead` (if member role resolves to `lead` from team membership data)

5) Multi-team membership hinting
- Member chips show additional-team context (`Also in: ...`) when a person is in multiple teams, to make matrix staffing obvious.

Acceptance expectation:
- A manager scanning the Org tab should identify hierarchy depth, accountable roles, and cross-team staffing without opening team edit screens.

ORG INTERACTION MODEL UPDATE (IMPLEMENTED 2026-03-23)

To support single-page business scanning, team cards follow a layered reveal model:

1) Default compact layer (always visible)
- Team name
- Member count
- Subteam count
- Team status

2) Leadership layer (collapsed by default)
- Manager
- Escalation Manager
- Explicit leadership visibility toggle per team

3) Team members layer (collapsed by default)
- Team member list with role badges (`Manager`, `Lead`)
- Additional-team context where relevant
- Explicit member visibility toggle per team

Global controls are expected to support quick reveal/collapse for:
- hierarchy expansion
- leadership layer
- member layer

Business overview objective:
- A user should be able to understand organizational shape, accountability, and immediate gaps from one screen before drilling into individual teams.

SEED/REGISTRY HARDENING

1) Schema-safe guards for optional/migrating tables
- Added safe table/column checks in seed and purge services.
- Integration-run registry now detects timestamp column dynamically:
  - prefer `created_at` when present
  - fallback to `started_at` when `created_at` is not present

2) Catalog idempotency by SKU
- Catalog seeding now upserts (`ON DUPLICATE KEY UPDATE`) by SKU.
- Repeat seed runs no longer emit duplicate-key noise for catalog entries.

3) Support SLA table tolerance
- Support seeding now guards missing SLA definitions table (`wp_pet_sla_definitions`) and degrades cleanly when absent.

4) Pulseway deterministic reseed
- Pulseway seeding now:
  - verifies required Pulseway tables exist before proceeding
  - reuses/updates a deterministic integration record (`Demo Pulseway Instance`)
  - clears deterministic prior Pulseway seed rows before reseed
  - avoids duplicate ticket-link inserts
  - avoids duplicate pulseway ticket creation by subject re-check

5) Conversation seeding reopen safety
- Conversation seeding now reopens resolved conversations before posting additional messages, preventing resolved-thread post failures during repeat seed operations.

PURGE HARDENING

1) Missing-table safe purge traversal
- Purge now checks table existence before attempting metadata/registry operations.
- Missing tables are marked as skipped rather than emitting avoidable warning paths.
- Specifically validated for missing `wp_pet_project_tasks`.

CAPACITY UTILIZATION HARDENING

- Utilization calculations now guard division-by-zero/non-finite values and normalize invalid outputs to `0.0`.

STAFF READINESS PERSONA COVERAGE (DETERMINISTIC)

Post-rerun readiness distribution remained stable:
- `ready=5`
- `partial=2`
- `incomplete=1`

VALIDATION EVIDENCE (2026-03-23)

Executed:
- `wp --path=/Users/stevecope/Sites/pet4 pet purge 9f2ba896-3734-43ff-9fe3-8a269a58eb73`
- `wp --path=/Users/stevecope/Sites/pet4 pet seed` → run `116eec82-e164-47a0-a320-6b318dd1a407`
- repeat `wp --path=/Users/stevecope/Sites/pet4 pet seed` → run `d1c74741-731b-4b74-a7a8-3d5921e8721c`

Outcome:
- targeted warning/error set resolved for non-staff seed paths
- repeated seeding remained stable and idempotent
- staff readiness persona distribution preserved

Known environmental noise outside seed logic:
- duplicate Xdebug load warning may still appear in CLI output depending on local PHP configuration
