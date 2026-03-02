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

Customers (4, was 2):
1. RPM Resources (Pty) Ltd
2. Acme Manufacturing SA (Pty) Ltd
3. Nexus Startup Labs (NEW)
4. Government Digital Services (NEW)

Sites (6, was 3):
- RPM Cape Town, RPM Johannesburg
- Acme Stellenbosch
- Nexus Cape Town (NEW)
- GDS Pretoria HQ (NEW), GDS Regional Office (NEW)

Contacts (7, was 4):
- Original 4 retained
- Tariq Hendricks (Nexus, NEW)
- Lisa van Wyk (Nexus, NEW)
- Thabo Dlamini (GDS, NEW)

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
