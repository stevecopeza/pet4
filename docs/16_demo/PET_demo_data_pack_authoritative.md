# PET Demo Data Pack (Authoritative, All-Encompassing)
Date: 2026-02-14  
Status: PROPOSED (becomes authoritative once accepted)  
Purpose: Provide a **single, authoritative** reference for **realistic demo activation**:
- what gets created (all tables)
- how it gets created (seed run, deterministic IDs, timestamps)
- how reset/purge works (removable unless “touched” / immutable)
- the **exact sample data** (fields + values) so TRAE only implements/injects what is specified here

This pack is aligned to the current migration-defined schema you provided (tables and columns).  
It also aligns to the QuickBooks-first spec (billing exports + QB shadow read models + outbox/event backbone) from:
- `PET_missing_pieces_spec_v3_DDD_QB_first.md`

---

## 0) Principles (binding for demo data)

1) **Deterministic + repeatable**
- Same seed run produces identical entities and relationships.
- IDs are deterministic (fixed UUID strings or fixed integer IDs as configured).

2) **Safe reset**
- Seeded data must be removable **unless** it has been “touched” by a human (edited) or has become immutable (accepted/submitted/queued/etc).
- Reset must never destroy immutable history; it may archive where supported.

3) **Believable timelines**
- Demo should look “alive”: tickets in flight with SLAs ticking, time entries across recent days, recent feed/announcements, realistic projects/quotes.

4) **QuickBooks-first**
- PET does not pretend to be accounting. Finance in demo is:
  - **Billing exports** created from time/baseline deliverables
  - **QB invoices/payments** mirrored into PET via shadow tables (mocked pull)

---

## 1) Seed run mechanics (how it does it)

### 1.1 Seed run identity
All demo-seeded rows MUST carry the following seed metadata:

**If table has `malleable_data` (JSON):**
- `malleable_data.seed_run_id` (string UUID)
- `malleable_data.seed_profile` (string, e.g. `demo_full`)
- `malleable_data.seeded_at` (datetime ISO)
- `malleable_data.touched_at` (datetime ISO, nullable)
- `malleable_data.touched_by_employee_id` (int/bigint, nullable)

**If table has `metadata_json` (JSON):**
- same keys under `metadata_json`

**If table has neither:**
- Add additive columns ONLY if required by implementation; otherwise keep a separate registry of seeded IDs in the seeder itself.
  - Preferred: do **not** alter existing tables for demo-only concerns.
  - For new tables added under v3, include `seed_run_id`, `seeded_at`, `touched_at` (if not already present).

### 1.2 “Touched” rules (what counts)
A row becomes “touched” when:
- any UI edit action changes any non-seed field, OR
- any transition occurs that represents real usage (e.g. quote accepted, time submitted, export queued).

Implementation must set:
- `malleable_data.touched_at = now()`
- `malleable_data.touched_by_employee_id = current_employee_id`

### 1.3 Timestamp normalization (what TRAE may adjust)
TRAE is allowed to adjust only:
- `created_at`, `updated_at`, `opened_at`, `resolved_at`, `closed_at`, `submitted_at`, `accepted_at`
- SLA due timestamps (`response_due_at`, `resolution_due_at`) derived from calendars

All other sample values must match this pack exactly.

### 1.4 Seed profiles
- `demo_full` (default): creates the full dataset described below.
- `demo_min` (optional): identity + one project + one quote + one ticket + one export + qb invoice/payment shadows.

---

## 2) Reset / purge (how it removes safely)

### 2.1 Delete vs archive
For tables WITH `archived_at`:
- If seeded AND touched: set `archived_at = now()` (do not delete)
- If seeded AND NOT touched: delete

For tables WITHOUT `archived_at` but with `status`:
- If seeded AND touched: set `status = archived` if enum supports; else do not delete
- If seeded AND NOT touched: delete

For append-only / immutable tables (never delete):
- event stream, outbox history, integration runs (depending), billing exports once queued

### 2.2 Immutable triggers (must be preserved)
Preserve (never delete) seeded rows when:
- `pet_quotes.accepted_at IS NOT NULL` OR `state` indicates accepted
- `pet_time_entries.submitted_at IS NOT NULL` OR status submitted/approved
- `pet_billing_exports.status IN (queued, sent, confirmed)`
- `pet_approval_requests.status IN (approved, rejected)`
- `pet_leave_requests.status IN (approved)`
- `pet_domain_event_stream` always preserve (append-only)

### 2.3 Purge order (to satisfy FK constraints)
Delete child tables first, then parents. Example order (partial):
1) reactions/acknowledgements/activity logs/feed events
2) work queues, work items
3) time entries
4) tickets
5) tasks
6) projects
7) baseline_components → baselines → contracts
8) quote component tables → quote lines → quotes
9) leads
10) contacts/affiliations → sites → customers
11) team_members → teams
12) employees (optional; usually keep demo employees)

---

## 3) Demo world narrative (what it does)

This dataset supports a demo story of:
1) A customer with multiple sites and contacts
2) A quote built from components: milestones/tasks/catalog items + recurring service with SLA snapshot
3) Contract + baseline snapshot
4) Project created from quote with tasks
5) Tickets created with SLA clocks running; escalations visible
6) Work orchestration: work items + department queues + priority scoring
7) People capability: roles, skills, proficiencies, certifications; assignments + KPIs
8) Time entry capture; some submitted (immutable) and some draft (editable)
9) Billing export to QuickBooks; QB invoice appears in PET; payment appears; customer balance reflects

---

## 4) Exact sample data (fields + values)

### 4.1 Identity

#### Employees (`pet_employees`)
Create 6 employees:

1) **Steve Admin**
- wp_user_id: 1
- first_name: Steve
- last_name: Admin
- email: steve.admin@demo.pet
- status: active
- hire_date: 2024-01-15
- manager_id: NULL
- calendar_id: 1
- malleable_schema_version: 1
- malleable_data: {"seed_profile":"demo_full","seed_run_id":"SEED-RUN-UUID","seeded_at":"2026-02-14T12:00:00"}  
- created_at: 2026-02-14 09:00:00
- archived_at: NULL

2) **Mia Manager**
- wp_user_id: 2
- first_name: Mia
- last_name: Manager
- email: mia.manager@demo.pet
- status: active
- hire_date: 2024-03-01
- manager_id: 1
- calendar_id: 1

3) **Liam Lead Tech**
- wp_user_id: 3
- first_name: Liam
- last_name: Tech
- email: liam.tech@demo.pet
- manager_id: 2
- calendar_id: 1

4) **Ava Consultant**
- wp_user_id: 4
- first_name: Ava
- last_name: Consultant
- email: ava.consultant@demo.pet
- manager_id: 2
- calendar_id: 1

5) **Noah Support**
- wp_user_id: 5
- first_name: Noah
- last_name: Support
- email: noah.support@demo.pet
- manager_id: 2
- calendar_id: 1

6) **Zoe Finance**
- wp_user_id: 6
- first_name: Zoe
- last_name: Finance
- email: zoe.finance@demo.pet
- manager_id: 1
- calendar_id: 1

> Notes:
> - manager relationships are required for realistic approvals and team views.
> - calendar_id must reference `pet_calendars.id` seeded below.

#### Customers (`pet_customers`)
Create 2 customers:

1) **RPM Resources (Demo)**
- name: RPM Resources
- legal_name: RPM Resources (Pty) Ltd
- contact_email: finance@rpm-demo.co.za
- status: active
- malleable_schema_version: 1
- created_at: 2025-08-01 10:00:00

2) **Acme Manufacturing (Demo)**
- name: Acme Manufacturing
- legal_name: Acme Manufacturing SA (Pty) Ltd
- contact_email: accounts@acme-demo.co.za
- status: active

#### Sites (`pet_sites`)
For RPM Resources:
- Site A: “RPM Cape Town” (city: Cape Town, country: ZA)
- Site B: “RPM Johannesburg” (city: Johannesburg, country: ZA)

For Acme:
- Site A: “Acme Stellenbosch”

Fields:
- customer_id, name, address_lines (JSON array or text per implementation), city, state, postal_code, country, status, malleable_schema_version, malleable_data, created_at, archived_at

#### Contacts (`pet_contacts`) + Affiliations (`pet_contact_affiliations`)
Create 4 contacts:
- RPM: “Priya Patel” (Finance), “John Mokoena” (Ops)
- Acme: “Sarah Jacobs” (IT), “David Naidoo” (GM)

Contacts hold personal details; affiliations define role + is_primary across customer/site.

---

### 4.2 Teams

#### Teams (`pet_teams`)
Create 3 teams:
1) Executive (manager_id = Steve, visual_type=icon, visual_ref=pet, status=active)
2) Delivery (parent=Executive, manager_id=Mia, escalation_manager_id=Steve)
3) Support (parent=Executive, manager_id=Noah, escalation_manager_id=Mia)

#### Team Members (`pet_team_members`)
- Delivery team: Mia, Liam, Ava
- Support team: Noah
- Executive: Steve, Zoe

Use:
- assigned_at = seeded_at
- removed_at = NULL

---

### 4.3 Calendars

#### Calendars (`pet_calendars`)
Create 1 default calendar:
- uuid: CAL-DEFAULT-UUID
- name: Standard ZA Business Hours
- timezone: Africa/Johannesburg
- is_default: 1

#### Working windows (`pet_calendar_working_windows`)
Mon–Fri:
- start_time: 08:30
- end_time: 17:00
- type: work
- rate_multiplier: 1.0

After-hours:
- start_time: 17:00
- end_time: 20:00
- type: afterhours
- rate_multiplier: 1.5

#### Holidays (`pet_calendar_holidays`)
Seed 2 holidays in the current year:
- New Year’s Day (2026-01-01, is_recurring=1)
- Freedom Day (2026-04-27, is_recurring=1)  (example)

---

### 4.4 Catalog

#### Catalog items (`pet_catalog_items`)
Create 6 items (sku UNIQUE):
- SKU: SRV-CONSULT-01 (type: service) “Consulting Hour” unit_price=1650 unit_cost=850 category=Services
- SKU: SRV-SUPPORT-01 “Support Hour” unit_price=1350 unit_cost=650
- SKU: PRD-LIC-01 (type: product) “Connector Licence” unit_price=12500 unit_cost=0
- SKU: PRD-TRAVEL-01 “Travel (per trip)” unit_price=950 unit_cost=450
- SKU: SRV-QBR-01 “QBR Session” unit_price=6500 unit_cost=2500
- SKU: SRV-ONBOARD-01 “Onboarding Pack” unit_price=35000 unit_cost=18000

Set `wbs_template` to a simple JSON stub for the items that drive delivery.

---

### 4.5 Work capability model (roles/skills/certs/KPIs)

#### Proficiency (`pet_proficiency_levels`)
Seed levels 1–5:
1 Beginner, 2 Competent, 3 Proficient, 4 Advanced, 5 Expert

#### Capabilities (`pet_capabilities`)
- “Implementation”
- “Support”
- “Finance Ops”

#### Skills (`pet_skills`)
Implementation:
- “SYSPRO Configuration”
- “Data Migration”
- “Workshop Facilitation”

Support:
- “Ticket Triage”
- “Root Cause Analysis”

Finance Ops:
- “Billing Export Review”
- “QuickBooks Reconciliation”

#### Roles (`pet_roles`)
- “Project Manager” level=3 status=published
- “Implementation Consultant” level=3 status=published
- “Support Technician” level=2 status=published
- “Finance Coordinator” level=2 status=published

#### Role skills (`pet_role_skills`)
- PM: Workshop Facilitation (min 3, weight 30), SYSPRO Config (min 2, weight 20)
- Impl Consultant: SYSPRO Config (min 3, 40), Data Migration (min 3, 30), Workshop (min 2, 20)
- Support Tech: Triage (min 3, 40), RCA (min 2, 30)
- Finance Coord: Billing Export Review (min 3, 50), QB Reconciliation (min 2, 30)

#### Certifications (`pet_certifications`)
- “SYSPRO Consultant Certification” issuing_body=SYSPRO expiry_months=24
- “ITIL Foundation” issuing_body=AXELOS expiry_months=36

#### Person role assignments (`pet_person_role_assignments`)
- Mia: Project Manager (allocation 60%)
- Liam: Implementation Consultant (allocation 80%)
- Ava: Implementation Consultant (allocation 70%)
- Noah: Support Technician (allocation 90%)
- Zoe: Finance Coordinator (allocation 80%)

#### Person skills (`pet_person_skills`)
- Liam: SYSPRO Config self=4 manager=4 effective_date 2025-11-01
- Ava: Workshop self=4 manager=3 effective_date 2025-11-01
- Noah: Triage self=4 manager=4
- Zoe: Billing Export Review self=4 manager=4

#### Person certifications (`pet_person_certifications`)
- Liam: SYSPRO Cert obtained 2025-03-01 expiry 2027-03-01
- Noah: ITIL obtained 2024-05-01 expiry 2027-05-01

#### KPI definitions (`pet_kpi_definitions`)
- “First Response Time” unit=minutes frequency=weekly
- “Billable Utilisation” unit=percent frequency=monthly
- “Invoice Turnaround” unit=days frequency=monthly

#### Role KPIs (`pet_role_kpis`)
- Support Tech: First Response Time weight 60 target 60 (min)
- Impl Consultant: Billable Utilisation weight 70 target 70 (%)
- Finance Coord: Invoice Turnaround weight 70 target 3 (days)

#### Person KPIs (`pet_person_kpis`)
For last full month:
- Noah: First Response Time actual 45 score 0.9 status=final
- Liam: Utilisation actual 68 score 0.97
- Zoe: Invoice Turnaround actual 2 score 1.0

---

### 4.6 Commercial

#### Leads (`pet_leads`)
Create 2 leads:
1) RPM: subject “SYSPRO Upgrade Phase 2” estimated_value 450000 status=open source=referral
2) Acme: subject “Support Retainer” estimated_value 120000 status=open source=website

#### Quotes (`pet_quotes`) + quote tables
Create 2 quotes for RPM:

**Quote Q-001 (Draft → Accepted)**
- title: “SYSPRO Upgrade Phase 2 – Delivery & Support”
- description: “Milestones, tasks, catalog items, and a recurring support service.”
- state: accepted
- version: 1
- total_value: 485000.00
- total_internal_cost: 290000.00
- currency: ZAR
- accepted_at: 2026-01-20 14:10:00

Quote lines (`pet_quote_lines`):
- “Onboarding Pack” qty 1 unit_price 35000 group=services
- “Consulting Hour” qty 120 unit_price 1650 group=services
- “Connector Licence” qty 2 unit_price 12500 group=products
- “QBR Session” qty 4 unit_price 6500 group=services

Quote components (`pet_quote_components`):
- Section “Implementation”: type=milestones
- Section “Recurring Services”: type=recurring_services
- Section “Catalog”: type=catalog_items

Milestones (`pet_quote_milestones`):
1) “Discovery & Planning”
2) “Migration & Cutover”
3) “Stabilisation”

Tasks (`pet_quote_tasks`) under milestones (duration_hours, role_id, rates):
- Discovery: “Kick-off Workshop” 6h role=PM base_internal_rate 900 sell_rate 1650
- Discovery: “Requirements Validation” 12h role=Impl sell 1650
- Migration: “Data Extraction” 16h role=Impl sell 1650
- Migration: “Migration Rehearsal” 20h role=Impl sell 1650
- Stabilisation: “Hypercare Support” 24h role=Support sell 1350

Recurring services (`pet_quote_recurring_services`):
- service_name: “Support Retainer – 20h/mo”
- sla_snapshot: JSON with response/resolution targets and calendar snapshot
- cadence: monthly
- term_months: 12
- renewal_model: auto
- sell_price_per_period: 27000
- internal_cost_per_period: 13000

Catalog items (`pet_quote_catalog_items`):
- Connector Licence qty 2 sku PRD-LIC-01
- Travel qty 3 sku PRD-TRAVEL-01

Payment schedule (`pet_quote_payment_schedule`):
- “Deposit” amount 150000 due 2026-01-25 paid_flag=1
- “Milestone 1” amount 175000 due 2026-02-20 paid_flag=0
- “Milestone 2” amount 160000 due 2026-03-20 paid_flag=0

**Quote Q-002 (Draft only)**
- title: “Additional QBR Package”
- state: draft
- accepted_at: NULL
- include 1–2 lines only (QBR Session)

---

### 4.7 Contracts & Baselines

#### Contracts (`pet_contracts`)
Create 1 contract from Q-001:
- quote_id: Q-001
- customer_id: RPM
- status: active
- total_value: 485000
- currency: ZAR
- start_date: 2026-02-01
- end_date: 2027-01-31

#### Baselines (`pet_baselines`)
- contract_id: contract above
- total_value: 485000
- total_internal_cost: 290000
- created_at: 2026-02-01 09:00:00

#### Baseline components (`pet_baseline_components`)
3 components:
- component_type: milestone “Discovery & Planning” sell 175000 cost 95000 component_data JSON (tasks snapshot)
- component_type: milestone “Migration & Cutover” sell 200000 cost 125000
- component_type: recurring_service “Support Retainer – 20h/mo” sell 27000/mo cost 13000/mo

---

### 4.8 Delivery

#### Projects (`pet_projects`)
Create 1 project for RPM:
- source_quote_id: Q-001
- name: “RPM SYSPRO Upgrade Phase 2”
- state: active
- sold_hours: 120
- sold_value: 198000
- start_date: 2026-02-03
- end_date: 2026-05-31
- malleable_schema_version: 1
- created_at: 2026-02-03 08:00:00

#### Tasks (`pet_tasks`)
Create 8 tasks linked to the project, mapping loosely to quote tasks:
- “Kick-off Workshop” estimated_hours 6 role=PM is_completed=1
- “Requirements Validation” 12 role=Impl is_completed=1
- “Data Extraction” 16 role=Impl is_completed=0
- “Migration Rehearsal” 20 role=Impl is_completed=0
- “Cutover Weekend” 12 role=Impl is_completed=0
- “Hypercare Week 1” 12 role=Support is_completed=0
- “Hypercare Week 2” 12 role=Support is_completed=0
- “QBR Session #1” 4 role=PM is_completed=0

---

### 4.9 Support + SLA

#### SLAs (`pet_slas`) + rules (`pet_sla_escalation_rules`)
Create 1 SLA:
- uuid: SLA-RPM-STD-UUID
- name: “RPM Standard Support SLA”
- status: published
- version_number: 1
- calendar_id: default calendar
- response_target_minutes: 60
- resolution_target_minutes: 480
- published_at: 2026-01-10 09:00:00

Rules:
- threshold_percent 50 action “notify_manager”
- threshold_percent 80 action “escalate_team”
- threshold_percent 100 action “breach_log”

#### Contract SLA snapshots (`pet_contract_sla_snapshots`)
Bind SLA to project:
- uuid: SLA-SNAP-RPM-UUID
- project_id: project above
- sla_original_id: SLA id
- sla_version_at_binding: 1
- sla_name_at_binding: “RPM Standard Support SLA”
- response_target_minutes: 60
- resolution_target_minutes: 480
- calendar_snapshot_json: snapshot of working windows + holidays
- bound_at: 2026-02-03 08:10:00

#### Tickets (`pet_tickets`)
Create 6 tickets (mix of states):
1) “Cannot post invoices to QB” priority=high status=open site=RPM Cape Town sla_snapshot_id=snapshot responded_at set, resolved_at NULL
2) “SYSPRO report slow” priority=medium status=in_progress responded_at NULL (to show breach risk)
3) “User access request” priority=low status=closed resolved/closed timestamps set
4) “Data migration validation” project-related
5) “Monthly support retainer check-in” status=open
6) “Integration webhook failure” status=in_progress

Populate:
- opened_at in last 14 days
- response_due_at/resolution_due_at derived from SLA + calendar
- include `sla_id` (original SLA) and `sla_snapshot_id` (snapshot)

#### SLA clock state (`pet_sla_clock_state`)
For each open/in_progress ticket create a clock row:
- ticket_id
- sla_version_id = SLA id or version reference as implemented
- warning_at, breach_at aligned to due times
- paused_flag = 0
- escalation_stage = 0/1 depending on progress
- last_evaluated_at recent
- last_event_dispatched recent

---

### 4.10 Work orchestration

#### Work items (`pet_work_items`)
Create work items for:
- all open/in_progress tickets (source_type=ticket)
- 3 project tasks (source_type=task) with scheduled_start/due

Populate:
- assigned_user_id: Noah for support tickets; Liam/Ava for project tasks
- department_id: 1 (Support) or 2 (Delivery) (seed dept ids consistent with existing system)
- required_role_id aligned to roles
- sla_snapshot_id for ticket-based items
- sla_time_remaining_minutes computed
- priority_score computed (seeded realistic values: 40–95)
- escalation_level set for at-risk ticket
- revenue set for billable items (optional)
- client_tier: “A” for RPM, “B” for Acme

#### Department queues (`pet_department_queues`)
For each work item, create a queue row:
- entered_queue_at, picked_up_at for some

---

### 4.11 Time

#### Time entries (`pet_time_entries`)
Create 20 entries across last 21 days, spread across Liam/Ava/Noah:
- mix of billable/non-billable
- durations 30–240 minutes
- link to task_id where relevant
- status: 12 draft, 6 submitted, 2 approved (if enum supports)
- submitted_at set for submitted rows
- archived_at NULL

---

### 4.12 Knowledge

#### Articles (`pet_articles`)
Create 6 articles:
- “How PET handles immutability”
- “QuickBooks integration overview”
- “SLA clocks explained”
- “Billing exports: from delivery to QB”
- “Roles and skills model”
- “Demo reset procedure”

Each:
- category (e.g. Operations/Finance/Support)
- status published
- created_by = Steve

---

### 4.13 Assets

#### Assets (`pet_assets`)
Attach 3 assets:
- to the project (entity_type=project)
- to ticket #1 (entity_type=ticket)
- to an article (entity_type=article)

file_path should reference a demo-safe file path within PET asset strategy.

---

### 4.14 Feed + announcements

#### Feed events (`pet_feed_events`)
Create 10 feed events across last 14 days:
- include a mix: “SLA warning”, “Billing export queued”, “New article published”, “Project milestone completed”
- audience_scope: team or customer
- pinned_flag for 1 event

#### Announcements (`pet_announcements`) + acknowledgements
Create 2 announcements:
1) “Demo: Please do not edit production settings” ack_required=1 acknowledgement_deadline in 7 days
2) “New SLA policy published” ack_required=0

Create acknowledgements for Steve and Mia for announcement #1.

#### Reactions (`pet_feed_reactions`)
Add a few reactions by employees to feed events.

---

### 4.15 Activity logs

#### Activity logs (`pet_activity_logs`)
Create 25 log entries across last 30 days:
- linked to quote, project, tickets, billing export
- user_id = employees
- type examples: `quote_created`, `ticket_assigned`, `export_queued`, `qb_invoice_synced`

---

### 4.16 Schema definitions

#### Schema definitions (`pet_schema_definitions`)
Seed schemas for entity types that use malleable_data:
- employees v1
- customers v1
- projects v1
- tickets v1
- articles v1

Set:
- status=published
- published_at recent
- created_by_employee_id = Steve

---

### 4.17 Phase 1 additions (from v3) — if implemented

#### Billing exports (`pet_billing_exports`, `pet_billing_export_items`)
Create 1 export for RPM:
- period_start: 2026-01-15
- period_end: 2026-01-31
- status: sent (or confirmed if you want “done”)

Items:
- 6 time_entry items (source_type=time_entry, source_id points at actual seeded time entries)
- 1 baseline_component item (source_type=baseline_component)
- 1 adjustment item (if adjustment source exists; otherwise omit)

#### QB invoices (`pet_qb_invoices`)
Upsert 1 invoice for RPM:
- qb_invoice_id: QB-INV-1001
- doc_number: INV-1001
- status: Open
- total: 185000
- balance: 65000
- raw_json: include minimal QB-like payload

#### QB payments (`pet_qb_payments`)
Upsert 1 payment for RPM:
- qb_payment_id: QB-PAY-9001
- amount: 120000
- applied_invoices_json references QB-INV-1001
- raw_json minimal

#### External mappings (`pet_external_mappings`)
Map:
- billing_export → qb_invoice_id
- customer → qb_customer_id (optional but recommended)

#### Integration runs (`pet_integration_runs`)
Create 2 runs:
- push success
- pull success

#### Event stream + outbox
- Insert events reflecting export lifecycle
- Insert outbox entry for the queued/sent export

---

## 5) Implementation constraints for TRAE (so nothing “falls off”)

TRAE MUST:
- Implement seeding strictly from this doc; no invented sample entities.
- Only adjust timestamps to keep the demo “recent”, without changing narrative ordering.
- Preserve relationships exactly (customer/site/contact/quote→contract→baseline→project/tasks; tickets→sla snapshots; work items→sources).
- Implement purge exactly per Section 2.
- Add an automated demo-seed validation test that asserts:
  - row counts per table (within a tolerance only for timestamp-driven due calculations)
  - referential integrity (no orphaned rows)
  - purge removes only untouched seeded rows
  - immutable rows survive purge

---

## 6) Seed/purge checklist (operator view)

Seed creates:
- 2 customers, 3 sites, 4 contacts, 4 affiliations
- 6 employees, 3 teams, memberships
- 1 calendar + windows + holidays
- catalog items
- roles/skills/certs/proficiency + assignments + KPIs
- leads + quotes + quote components + payment schedule
- contract + baseline + components
- project + tasks
- SLA + snapshot + tickets + SLA clocks
- work items + queues
- time entries
- knowledge articles
- assets
- feed/announcements/reactions
- activity logs
- schema definitions
- billing export + QB invoice/payment shadows (if Phase 1 additions exist)

Purge guarantees:
- untouched seeded data removed cleanly
- touched/immutable preserved (archived if possible)
- event stream preserved

---

## 7) TODO markers (only if you accept changes)
If any field type/enum differs in your implementation, correct the schema first and then update this pack.  
This pack is intended to be the single authoritative reference for demo activation once accepted.
