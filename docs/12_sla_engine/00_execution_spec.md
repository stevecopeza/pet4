# PET SLA Engine v1.0 -- Developer Execution Specification

Status: Authoritative Scope: SLA Builder → Versioning → Contract
Snapshot → KPI Binding → Escalation → Reporting Audience: Developers,
Architects, Technical Leads

  ------------------------------
  1\. ARCHITECTURAL PRINCIPLES
  ------------------------------

1.  SLA is a first-class Commercial Domain entity.
2.  Published SLA versions are immutable.
3.  Contracts bind to SLA snapshot at acceptance.
4.  KPI definitions auto-generated and bound to roles.
5.  Breach detection is SLA-calendar aware.
6.  Escalations are event-driven and structured.
7.  No SLA drift across versions or contracts.

  ---------------------
  2\. DATABASE SCHEMA
  ---------------------

  : slas - id (UUID, PK) - name (varchar 255, required) - tier (enum:
  bronze, silver, gold, custom) - description (text) - version_number
  (int, required) - status (enum: draft, published, deprecated,
  archived) - response_time_target_minutes (int, nullable — NULL for
  tiered SLAs) - resolution_time_target_minutes (int, nullable — NULL
  for tiered SLAs) - calendar_id (UUID, FK, nullable — NULL for tiered
  SLAs) - tier_transition_cap_percent (int, default 80) - created_at
  (datetime) - updated_at (datetime)

Unique constraint: (name, version_number)

For flat response/resolution targets: retained for single-tier backward
compatibility. For tiered SLAs: targets live in `sla_tiers` table.
See docs_27_sla_engine_08_tiered_sla_spec.md for full tiered model.

Table: sla_tiers - id (UUID, PK) - sla_id (UUID, FK) - priority (int,
required) - calendar_id (UUID, FK, required) -
response_target_minutes (int, required) - resolution_target_minutes
(int, required). Unique constraint: (sla_id, priority)

Table: sla_escalation_rules - id (UUID, PK) - sla_id (UUID, FK,
nullable — for single-tier SLAs) - sla_tier_id (UUID, FK, nullable —
for tiered SLAs) - threshold_percent (int, required) -
escalation_role_id (UUID) - escalation_level (int) - notify_method
(enum: email, sms, internal)

Table: sla_clock_tier_transitions - id (UUID, PK) - ticket_id
(UUID, FK) - from_tier_priority (int) - to_tier_priority (int) -
actual_percent_at_transition (decimal 5,2) - carried_percent
(decimal 5,2) - override_reason (text, nullable) - transitioned_at
(datetime, UTC)

Table: sla_exclusions - id (UUID, PK) - sla_id (UUID, FK) -
exclusion_type (enum: public_holiday, customer_delay, third_party) -
description (text)

Table: sla_service_credit_rules - id (UUID, PK) - sla_id (UUID, FK) -
breach_threshold_percent (int) - credit_percentage (decimal 5,2) -
max_credit_cap_percent (decimal 5,2)

Table: contract_sla_snapshots - id (UUID, PK) - contract_id (UUID) -
sla_id (UUID) - sla_version_number (int) - snapshot_json (json,
required) - bound_role_id (UUID)

Indexes: - sla_id - contract_id - status

  ------------------
  3\. DOMAIN MODEL
  ------------------

Entity: SLA Methods: - publish() - deprecate() - archive() -
validateForPublish() - generateKpiTemplates()

Invariant: - Cannot edit when status = published

Entity: SLAEscalationRule - evaluateThreshold(currentPercent)

Entity: SLAServiceCreditRule - calculateCredit(breachPercent)

  -------------------------
  4\. KPI AUTO-GENERATION
  -------------------------

On SLA Publish:

Generate KPI Templates: - ResponseTimeCompliance -
ResolutionTimeCompliance - BreachCount - EscalationCount

Each KPI: - Bound to Role - Reads from contract_sla_snapshots -
Aggregation interval configurable (daily/monthly)

  --------------------------------
  5\. BREACH DETECTION ALGORITHM
  --------------------------------

Input: - Ticket created_at - First response_at - Resolution_at - SLA
snapshot - Calendar engine

For single-tier SLAs:
1. Calculate business minutes between created_at and response_at.
2. Compare to response_time_target_minutes.
3. Calculate business minutes between created_at and resolution_at.
4. Compare to resolution_time_target_minutes.
5. Compute percent_of_target = elapsed / target.
6. If percent \>= escalation threshold → trigger escalation event.
7. If percent \>= 100% → record breach.

For tiered SLAs:
1. Determine active tier (see docs_27_sla_engine_08_tiered_sla_spec.md §3).
2. Calculate business minutes using the **active tier's calendar**.
3. At tier boundary crossings, apply carry-forward algorithm with
   configurable cap (default 80%).
4. Escalation thresholds evaluate against current tier's target.
5. Breach at 100% of current tier → logged per tier. Multiple breaches
   possible across tier transitions.

Time calculation MUST use SLA calendar (or active tier calendar) and
UTC normalization.

  -----------------------
  6\. ESCALATION ENGINE
  -----------------------

Events: - SLABreachWarning - SLABreachOccurred - SLAEscalationTriggered

On threshold match: - Determine escalation_role_id - Dispatch
notification - Record escalation log entry

Escalations are idempotent per threshold level.

  ------------------
  7\. API CONTRACT
  ------------------

POST /slas - Create Draft SLA

PUT /slas/{id} - Update Draft SLA

POST /slas/{id}/publish - Publish SLA (validates & auto-generates KPIs)

POST /slas/{id}/deprecate

GET /slas?status=published

POST /contracts/{id}/bind-sla - Bind SLA version to contract - Snapshot
stored

Validation Errors: - Cannot publish without escalation rules - Cannot
publish without calendar - Cannot modify published SLA

  -----------------
  8\. UX CONTRACT
  -----------------

SLA Builder: Sections: - Overview - Targets (or Tiers for tiered SLAs) -
Operating Calendar(s) - Escalations (per tier) - Exclusions -
Service Credits - Tier Transition Cap (tiered SLAs only)

Warnings: - Publishing creates immutable version. - Deprecated SLAs
cannot be used in new quotes. - Archived SLAs hidden but retained
historically.

Sales UI: - Select SLA tier only (no editing)

  ------------------------
  9\. PERMISSIONS MATRIX
  ------------------------

Commercial Management: - Create/Edit Draft SLA - Publish SLA - Deprecate
SLA

Sales: - Select Published SLA in Quote

Support: - View SLA snapshot (read-only)

Finance: - View service credit exposure

  ---------------------
  10\. TEST SCENARIOS
  ---------------------

1.  Publish SLA without escalation rule → blocked.
2.  Modify published SLA → blocked.
3.  Ticket breaches at 80% threshold → warning triggered.
4.  Ticket exceeds 100% → breach logged.
5.  SLA deprecated → cannot attach to new Quote.
6.  Contract termination → SLA snapshot remains for history.
7.  Tiered SLA: ticket crosses tier boundary → carry-forward applied.
8.  Tiered SLA: breach in tier 1, transition to tier 2 at cap%.
9.  Tiered SLA: publish with calendar coverage gap → blocked.
10. Tiered SLA: manual tier override → logged with reason.

  ---------------------------
  11\. IMPLEMENTATION ORDER
  ---------------------------

1.  Migrations
2.  Domain Entities & Invariants
3.  SLA Builder UI
4.  KPI Template Generation
5.  Escalation Engine
6.  Contract Snapshot Logic
7.  Reporting Dashboard

  -------------------------------------------
  12\. EVENT MAPPING (DOCUMENTATION → CODE)
  -------------------------------------------

Documentation terminology differs from implementation event class names.
The following mapping applies:

- SLABreachWarning → `TicketWarningEvent` (`Domain\Support\Event\TicketWarningEvent`)
- SLABreachOccurred → `TicketBreachedEvent` (`Domain\Support\Event\TicketBreachedEvent`)
- SLAEscalationTriggered → `EscalationTriggeredEvent` (`Domain\Support\Event\EscalationTriggeredEvent`)
- SLATierTransitioned → `SLATierTransitionedEvent` (`Domain\Support\Event\SLATierTransitionedEvent`)

When tracing from this specification to code, use the code class names above.
Documentation event names are retained for conceptual clarity.

  ----------------------
  END OF SPECIFICATION
  ----------------------
